<?php

declare(strict_types=1);

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Imports\ImportColumn;
use Filament\Schemas\Components\Component;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Filament\Integration\Builders\BaseBuilder;
use Relaticle\CustomFields\Filament\Integration\Builders\Concerns\ResolvesFields;
use Relaticle\CustomFields\Filament\Integration\Builders\ExporterBuilder;
use Relaticle\CustomFields\Filament\Integration\Builders\InfolistBuilder;
use Relaticle\CustomFields\Filament\Integration\Builders\TableBuilder;
use Relaticle\CustomFields\Filament\Integration\CustomFieldsManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

mutates(CustomFieldsManager::class, BaseBuilder::class, ResolvesFields::class, TableBuilder::class, InfolistBuilder::class, ExporterBuilder::class);

describe('native builder configuration', function (): void {
    beforeEach(function (): void {
        seedTwoSections();
    });

    it('applies container defaults only to exporters', function (): void {
        app()->resolving(ExporterBuilder::class, static function (ExporterBuilder $builder): void {
            $builder->filterFieldsUsing(fn (Collection $fields): Collection => $fields
                ->reject(fn (CustomField $field): bool => $field->code === 'cost'));
        });

        $exportNames = CustomFields::exporter()->forModel(Post::class)->columns()
            ->map(fn (ExportColumn $column): string => $column->getName());

        expect($exportNames)->not->toContain('custom_fields.cost')
            ->and(componentNames(CustomFields::table()->forModel(Post::class)->columns()))
            ->toContain('custom_fields.cost');
    });

    it('keeps local filters isolated from later builders', function (): void {
        $filtered = CustomFields::table()->forModel(Post::class)
            ->filterFieldsUsing(fn (Collection $fields): Collection => $fields->where('code', 'headline'));

        expect(componentNames($filtered->columns()))->toBe(['custom_fields.headline'])
            ->and(componentNames(CustomFields::table()->forModel(Post::class)->columns()))
            ->toBe(['custom_fields.headline', 'custom_fields.featured', 'custom_fields.reviewer_notes', 'custom_fields.cost']);
    });

    it('exposes selected fields through native fluent and collection methods', function (bool $includeCost, array $expectedCodes): void {
        $builder = CustomFields::table()->forModel(Post::class)
            ->when(true, fn (TableBuilder $builder): TableBuilder => $builder->only(['headline', 'featured', 'cost']))
            ->unless($includeCost, fn (TableBuilder $builder): TableBuilder => $builder->except(['cost']))
            ->tap(function (TableBuilder $builder): void {
                $builder->filterFieldsUsing(fn (Collection $fields): Collection => $fields->where('code', '!=', 'headline'));
            });

        expect($builder->getFields()->pluck('code')->all())->toBe($expectedCodes)
            ->and(componentNames($builder->columns()))
            ->toBe(array_map(fn (string $code): string => 'custom_fields.'.$code, $expectedCodes));
    })->with([
        [false, ['featured']],
        [true, ['featured', 'cost']],
    ]);

    it('passes fields from surviving sections to field callbacks', function (): void {
        $seenCodes = [];
        $builder = CustomFields::table()->forModel(Post::class)
            ->filterFieldsUsing(function (Collection $fields) use (&$seenCodes): Collection {
                $seenCodes = $fields->pluck('code')->all();

                return $fields->where('code', 'featured');
            })
            ->filterSectionsUsing(fn (Collection $sections): Collection => $sections->where('code', 'public'));

        expect(componentNames($builder->columns()))->toBe(['custom_fields.featured'])
            ->and($seenCodes)->toBe(['headline', 'featured']);
    });

    it('exposes an unsaved model class without exposing it as a persisted record', function (): void {
        $builder = CustomFields::table()->forModel(new Post);

        expect($builder->getModel())->toBe(Post::class)
            ->and($builder->getRecord())->toBeNull()
            ->and($builder->getFields())->toHaveCount(4);
    });

    it('keeps returned field and section collections independent from cached metadata', function (): void {
        $builder = CustomFields::table()->forModel(Post::class);

        $builder->getFields()->shift();
        $builder->getSections()->first()->fields->shift();

        expect($builder->getFields()->pluck('code')->all())
            ->toBe(['headline', 'featured', 'reviewer_notes', 'cost'])
            ->and(componentNames($builder->columns()))
            ->toBe(['custom_fields.headline', 'custom_fields.featured', 'custom_fields.reviewer_notes', 'custom_fields.cost']);
    });

    it('returns empty metadata before binding a model', function (bool $sectionsEnabled): void {
        $features = FeatureConfigurator::configure()->enable(CustomFieldsFeature::SYSTEM_SECTIONS);

        if (! $sectionsEnabled) {
            $features->disable(CustomFieldsFeature::SYSTEM_SECTIONS);
        }

        config()->set('custom-fields.features', $features);

        foreach ([CustomFields::table(), CustomFields::infolist(), CustomFields::exporter()] as $builder) {
            expect($builder->getFields())->toBeEmpty()
                ->and($builder->getSections())->toBeEmpty()
                ->and($builder->getModel())->toBeNull()
                ->and($builder->getRecord())->toBeNull();
        }
    })->with([true, false]);

    it('returns no sections after sections are disabled', function (): void {
        $builder = CustomFields::table()->forModel(Post::class);

        expect($builder->getSections())->toHaveCount(2);

        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::UI_TABLE_COLUMNS)
            ->disable(CustomFieldsFeature::SYSTEM_SECTIONS));

        expect($builder->getSections())->toBeEmpty()
            ->and($builder->getFields()->pluck('code')->all())
            ->toBe(['headline', 'featured', 'reviewer_notes', 'cost']);
    });

    it('resolves fields directly when sections are disabled', function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::UI_TABLE_COLUMNS)
            ->disable(CustomFieldsFeature::SYSTEM_SECTIONS));

        $builder = CustomFields::table()->forModel(Post::class)
            ->filterFieldsUsing(fn (Collection $fields): Collection => $fields->where('code', 'headline'));

        expect($builder->getFields()->pluck('code')->all())->toBe(['headline'])
            ->and($builder->getSections())->toBeEmpty()
            ->and(componentNames($builder->columns()))->toBe(['custom_fields.headline']);
    });

    it('removes sections when no selected fields remain', function (): void {
        $builder = CustomFields::table()->forModel(Post::class)
            ->filterFieldsUsing(fn (Collection $fields): Collection => $fields->whereIn('code', []));

        expect($builder->getFields())->toBeEmpty()
            ->and($builder->getSections())->toBeEmpty()
            ->and($builder->columns())->toBeEmpty();
    });

    it('evaluates filters with the current record after rebinding a builder', function (): void {
        $first = Post::factory()->create();
        $second = Post::factory()->create();
        $builder = CustomFields::table()->forModel($first)
            ->filterFieldsUsing(fn (Collection $fields, TableBuilder $builder): Collection => $fields
                ->where('code', $builder->getRecord()?->is($first) ? 'headline' : 'featured'));

        expect(componentNames($builder->columns()))->toBe(['custom_fields.headline'])
            ->and(componentNames($builder->forModel($second)->columns()))->toBe(['custom_fields.featured']);
    });
});

/** @param Closure(Collection<int, CustomField>, BaseBuilder): Collection<int, CustomField> $callback */
function configureFieldFilter(Closure $callback): void
{
    foreach ([TableBuilder::class, InfolistBuilder::class, ExporterBuilder::class] as $builderClass) {
        app()->resolving($builderClass, function (TableBuilder|InfolistBuilder|ExporterBuilder $builder) use ($callback): void {
            $builder->filterFieldsUsing($callback);
        });
    }
}

/** @param Closure(Collection<int, CustomFieldSection>, BaseBuilder): Collection<int, CustomFieldSection> $callback */
function configureSectionFilter(Closure $callback): void
{
    foreach ([TableBuilder::class, InfolistBuilder::class, ExporterBuilder::class] as $builderClass) {
        app()->resolving($builderClass, function (TableBuilder|InfolistBuilder|ExporterBuilder $builder) use ($callback): void {
            $builder->filterSectionsUsing($callback);
        });
    }
}

/**
 * @return array{public: CustomFieldSection, internal: CustomFieldSection}
 */
function seedTwoSections(): array
{
    $public = CustomFieldSection::factory()->forEntityType(Post::class)->create(['name' => 'Public', 'code' => 'public', 'sort_order' => 1]);
    $internal = CustomFieldSection::factory()->forEntityType(Post::class)->create(['name' => 'Internal', 'code' => 'internal', 'sort_order' => 2]);

    CustomField::factory()->create(['custom_field_section_id' => $public->id, 'entity_type' => Post::class, 'name' => 'Headline', 'code' => 'headline', 'type' => 'text']);
    CustomField::factory()->ofType('select')->withOptions(['Yes', 'No'])->create(['custom_field_section_id' => $public->id, 'entity_type' => Post::class, 'name' => 'Featured', 'code' => 'featured']);
    CustomField::factory()->create(['custom_field_section_id' => $internal->id, 'entity_type' => Post::class, 'name' => 'Reviewer Notes', 'code' => 'reviewer_notes', 'type' => 'text']);
    CustomField::factory()->create(['custom_field_section_id' => $internal->id, 'entity_type' => Post::class, 'name' => 'Cost', 'code' => 'cost', 'type' => 'text']);

    return ['public' => $public, 'internal' => $internal];
}

/**
 * @param  Collection<int, Column|BaseFilter>  $components
 * @return array<int, string>
 */
function componentNames(Collection $components): array
{
    return $components->map(fn (Column|BaseFilter $component): string => $component->getName())->values()->all();
}

describe('table builder', function (): void {
    beforeEach(function (): void {
        seedTwoSections();
    });

    it('renders every field when no filter is registered', function (): void {
        expect(componentNames(CustomFields::table()->forModel(Post::class)->columns()))
            ->toBe(['custom_fields.headline', 'custom_fields.featured', 'custom_fields.reviewer_notes', 'custom_fields.cost'])
            ->and(componentNames(CustomFields::table()->forModel(Post::class)->filters()))
            ->toBe(['custom_fields.featured']);
    });

    it('drops fields rejected by a field filter from columns and filters', function (): void {
        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields
            ->reject(fn (CustomField $field): bool => in_array($field->code, ['featured', 'cost'], true)));

        expect(componentNames(CustomFields::table()->forModel(Post::class)->columns()))
            ->toBe(['custom_fields.headline', 'custom_fields.reviewer_notes'])
            ->and(componentNames(CustomFields::table()->forModel(Post::class)->filters()))
            ->toBe([]);
    });

    it('drops every field of a section rejected by a section filter', function (): void {
        configureSectionFilter(fn (Collection $sections, BaseBuilder $builderContext): Collection => $sections
            ->reject(fn (CustomFieldSection $section): bool => $section->code === 'internal'));

        expect(componentNames(CustomFields::table()->forModel(Post::class)->columns()))
            ->toBe(['custom_fields.headline', 'custom_fields.featured']);
    });

    it('hands the table builder context to the filter', function (): void {
        $seen = null;

        configureFieldFilter(function (Collection $fields, BaseBuilder $builderContext) use (&$seen): Collection {
            $seen = $builderContext;

            return $fields;
        });

        CustomFields::table()->forModel(Post::class)->columns();

        expect($seen)->toBeInstanceOf(TableBuilder::class)
            ->and($seen::class)->toBe(TableBuilder::class)
            ->and($seen->getModel())->toBe(Post::class)
            ->and($seen->getRecord())->toBeNull();
    });

    it('composes several field filters in registration order', function (): void {
        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields
            ->reject(fn (CustomField $field): bool => $field->code === 'headline'));
        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields
            ->reject(fn (CustomField $field): bool => $field->code === 'cost'));

        expect(componentNames(CustomFields::table()->forModel(Post::class)->columns()))
            ->toBe(['custom_fields.featured', 'custom_fields.reviewer_notes']);
    });

    it('still evaluates a condition against a field the filter removed', function (): void {
        $public = CustomFieldSection::query()->where('code', 'public')->sole();
        $promoCopy = CustomField::factory()
            ->conditionallyVisible('headline', VisibilityOperator::EQUALS->value, 'Sale')
            ->create(['custom_field_section_id' => $public->id, 'entity_type' => Post::class, 'name' => 'Promo Copy', 'code' => 'promo_copy', 'type' => 'text']);

        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields
            ->reject(fn (CustomField $field): bool => $field->code === 'headline'));

        $post = Post::factory()->create();
        $post->saveCustomFieldValue(CustomField::query()->where('code', 'headline')->sole(), 'Sale');
        $post->saveCustomFieldValue($promoCopy, 'Buy now');
        $post->refresh()->load('customFieldValues.customField.options');

        $builder = CustomFields::table()->forModel(Post::class);

        $column = $builder->columns()
            ->first(fn (Column $column): bool => $column->getName() === 'custom_fields.promo_copy');

        expect($column)->not->toBeNull()
            ->and($column->record($post)->formatState('Buy now'))->toBe('Buy now');

        $secondColumn = $builder->columns()
            ->first(fn (Column $column): bool => $column->getName() === 'custom_fields.promo_copy');

        expect($secondColumn)->not->toBeNull()
            ->and($secondColumn->record($post)->formatState('Buy now'))->toBe('Buy now');
    });

    it('returns no columns from a builder that was never given a model', function (): void {
        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields);

        expect(CustomFields::table()->columns())->toBeEmpty();
    });

    it('hands the field filter the whole entity field set once, not one slice per section', function (): void {
        $counts = [];

        configureFieldFilter(function (Collection $fields, BaseBuilder $builderContext) use (&$counts): Collection {
            $counts[] = count($fields);

            return $fields;
        });

        CustomFields::table()->forModel(Post::class)->columns();

        expect($counts)->toBe([4]);
    });

    it('lets a field filter reason across sections', function (): void {
        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields->contains(fn (CustomField $field): bool => $field->code === 'headline')
            ? $fields->reject(fn (CustomField $field): bool => $field->code === 'cost')
            : $fields);

        expect(componentNames(CustomFields::table()->forModel(Post::class)->columns()))
            ->toBe(['custom_fields.headline', 'custom_fields.featured', 'custom_fields.reviewer_notes']);
    });

    it('replaces section constraints when a builder is reused', function (): void {
        $public = CustomFieldSection::query()->where('code', 'public')->sole();
        $internal = CustomFieldSection::query()->where('code', 'internal')->sole();
        $builder = CustomFields::table()->forModel(Post::class)->onlySections([$public->id]);

        expect(componentNames($builder->columns()))->toBe(['custom_fields.headline', 'custom_fields.featured']);

        expect(componentNames($builder->onlySections([$internal->id])->columns()))
            ->toBe(['custom_fields.reviewer_notes', 'custom_fields.cost']);

        expect(componentNames($builder->onlySections([])->columns()))->toHaveCount(4);
    });

    it('keeps cached fields intact when a filter mutates its input', function (): void {
        config()->set('custom-fields.testing_filter_enabled', true);
        $builder = CustomFields::table()->forModel(Post::class)
            ->filterFieldsUsing(function (Collection $fields): Collection {
                if (config('custom-fields.testing_filter_enabled')) {
                    $fields->forget($fields->search(fn (CustomField $field): bool => $field->code === 'cost'));
                }

                return $fields;
            });

        expect(componentNames($builder->columns()))
            ->toBe(['custom_fields.headline', 'custom_fields.featured', 'custom_fields.reviewer_notes']);

        config()->set('custom-fields.testing_filter_enabled', false);

        expect(componentNames($builder->columns()))->toContain('custom_fields.cost');
    });

    it('keeps section field collections intact when a filter mutates them', function (): void {
        config()->set('custom-fields.testing_filter_enabled', true);
        $builder = CustomFields::table()->forModel(Post::class)
            ->filterSectionsUsing(function (Collection $sections): Collection {
                if (config('custom-fields.testing_filter_enabled')) {
                    $sections->each(function (CustomFieldSection $section): void {
                        $section->fields->shift();
                    });
                }

                return $sections;
            });

        expect(componentNames($builder->columns()))->toBe(['custom_fields.featured', 'custom_fields.cost'])
            ->and(componentNames($builder->columns()))->toBe(['custom_fields.featured', 'custom_fields.cost']);

        config()->set('custom-fields.testing_filter_enabled', false);

        expect(componentNames($builder->columns()))
            ->toBe(['custom_fields.headline', 'custom_fields.featured', 'custom_fields.reviewer_notes', 'custom_fields.cost']);
    });
});

describe('table builder query efficiency', function (): void {
    it('loads field metadata once per builder even with a filter registered', function (): void {
        $section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['name' => 'Public', 'code' => 'public']);

        CustomField::factory()->create(['custom_field_section_id' => $section->id, 'entity_type' => Post::class, 'name' => 'Headline', 'code' => 'headline', 'type' => 'text']);

        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields);

        $customFieldsTable = config('custom-fields.database.table_names.custom_fields');

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $builder = CustomFields::table()->forModel(Post::class);
            $builder->getFields();
            $builder->columns();

            $customFieldQueries = array_filter(DB::getQueryLog(), static fn (array $entry): bool => str_contains($entry['query'], '"'.$customFieldsTable.'"')
                || str_contains($entry['query'], '`'.$customFieldsTable.'`'));

            expect($customFieldQueries)->toHaveCount(1);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    });
});

describe('exporter and importer builders', function (): void {
    beforeEach(function (): void {
        seedTwoSections();
    });

    it('exports every field when no filter is registered', function (): void {
        $names = CustomFields::exporter()->forModel(Post::class)->columns()
            ->map(fn (ExportColumn $column): string => $column->getName())->values()->all();

        expect($names)->toBe(['custom_fields.headline', 'custom_fields.featured', 'custom_fields.reviewer_notes', 'custom_fields.cost']);
    });

    it('drops a filtered field from export columns and passes the exporter builder', function (): void {
        $builders = [];

        configureFieldFilter(function (Collection $fields, BaseBuilder $builderContext) use (&$builders): Collection {
            $builders[] = $builderContext::class;

            return $fields->reject(fn (CustomField $field): bool => $field->code === 'cost');
        });

        $names = CustomFields::exporter()->forModel(Post::class)->columns()
            ->map(fn (ExportColumn $column): string => $column->getName())->values()->all();

        expect($names)->toBe(['custom_fields.headline', 'custom_fields.featured', 'custom_fields.reviewer_notes'])
            ->and($builders)->toBe([ExporterBuilder::class]);
    });

    it('leaves importer columns untouched by a field filter', function (): void {
        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields
            ->reject(fn (CustomField $field): bool => $field->code === 'cost'));

        $names = CustomFields::importer()->forModel(Post::class)->columns()
            ->map(fn (ImportColumn $column): string => $column->getName())->values()->all();

        expect($names)->toContain('custom_fields_cost');
    });
});

/**
 * @return array<int, string>
 */
function infolistFieldNames(Component $section): array
{
    return array_map(
        fn (Component $entry): string => $entry->getName(),
        $section->getDefaultChildComponents(),
    );
}

/**
 * @return array<string, array<int, string>> section heading => entry names
 */
function infolistShape(Post $post): array
{
    return CustomFields::infolist()->forModel($post)->values()
        ->mapWithKeys(fn (Component $section): array => [$section->getHeading() => infolistFieldNames($section)])
        ->all();
}

describe('infolist builder', function (): void {
    beforeEach(function (): void {
        seedTwoSections();
        $this->post = Post::factory()->create();
    });

    it('renders every section and field when no filter is registered', function (): void {
        expect(infolistShape($this->post))->toBe([
            'Public' => ['custom_fields.headline', 'custom_fields.featured'],
            'Internal' => ['custom_fields.reviewer_notes', 'custom_fields.cost'],
        ]);
    });

    it('removes a filtered section and its fields', function (): void {
        configureSectionFilter(fn (Collection $sections, BaseBuilder $builderContext): Collection => $sections
            ->reject(fn (CustomFieldSection $section): bool => $section->code === 'internal'));

        expect(infolistShape($this->post))->toBe([
            'Public' => ['custom_fields.headline', 'custom_fields.featured'],
        ]);
    });

    it('removes a filtered field and drops a section left empty', function (): void {
        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields
            ->reject(fn (CustomField $field): bool => in_array($field->code, ['reviewer_notes', 'cost'], true)));

        expect(infolistShape($this->post))->toBe([
            'Public' => ['custom_fields.headline', 'custom_fields.featured'],
        ]);
    });

    it('passes the bound infolist builder to filters', function (): void {
        $seen = null;

        configureFieldFilter(function (Collection $fields, BaseBuilder $builderContext) use (&$seen): Collection {
            $seen = $builderContext;

            return $fields;
        });

        CustomFields::infolist()->forModel($this->post)->values();

        expect($seen::class)->toBe(InfolistBuilder::class)
            ->and($seen->getRecord()?->is($this->post))->toBeTrue();
    });

    it('keeps a field whose condition depends on a filtered-out field in another section', function (): void {
        $internal = CustomFieldSection::query()->where('code', 'internal')->sole();
        CustomField::factory()
            ->conditionallyVisible('headline', VisibilityOperator::EQUALS->value, 'Sale')
            ->create(['custom_field_section_id' => $internal->id, 'entity_type' => Post::class, 'name' => 'Promo Copy', 'code' => 'promo_copy', 'type' => 'text']);

        $this->post->saveCustomFieldValue(CustomField::query()->where('code', 'headline')->sole(), 'Sale');
        $this->post->refresh();

        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields
            ->reject(fn (CustomField $field): bool => $field->code === 'headline'));

        expect(infolistShape($this->post)['Internal'])->toContain('custom_fields.promo_copy');
    });

    it('gives two same-code fields in different sections their own visibility verdict', function (): void {
        $public = CustomFieldSection::query()->where('code', 'public')->sole();
        $internal = CustomFieldSection::query()->where('code', 'internal')->sole();

        CustomField::factory()
            ->create(['custom_field_section_id' => $public->id, 'entity_type' => Post::class, 'name' => 'Promo Copy', 'code' => 'promo_copy', 'type' => 'text']);
        CustomField::factory()
            ->conditionallyVisible('headline', VisibilityOperator::EQUALS->value, 'Sale')
            ->create(['custom_field_section_id' => $internal->id, 'entity_type' => Post::class, 'name' => 'Promo Copy', 'code' => 'promo_copy', 'type' => 'text']);

        expect(infolistShape($this->post)['Public'])->toContain('custom_fields.promo_copy')
            ->and(infolistShape($this->post)['Internal'])->not->toContain('custom_fields.promo_copy');
    });

    it('evaluates duplicate condition codes using the field from the same section', function (): void {
        foreach (['public' => 'Published', 'internal' => 'Reviewed'] as $sectionCode => $status) {
            $section = CustomFieldSection::query()->where('code', $sectionCode)->sole();
            $statusField = CustomField::factory()->create([
                'custom_field_section_id' => $section->id,
                'entity_type' => Post::class,
                'name' => 'Status',
                'code' => 'status',
                'type' => 'text',
            ]);
            CustomField::factory()
                ->conditionallyVisible('status', VisibilityOperator::EQUALS->value, $status)
                ->create([
                    'custom_field_section_id' => $section->id,
                    'entity_type' => Post::class,
                    'name' => 'Summary',
                    'code' => 'summary',
                    'type' => 'text',
                ]);

            $this->post->saveCustomFieldValue($statusField, $status);
        }

        $this->post->refresh();

        expect(infolistShape($this->post)['Public'])->toContain('custom_fields.summary')
            ->and(infolistShape($this->post)['Internal'])->toContain('custom_fields.summary');
    });

    it('returns no entries from a builder that was never given a model', function (): void {
        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields);

        expect(CustomFields::infolist()->values())->toBeEmpty();
    });

    it('leaves the form builder untouched by a field filter', function (): void {
        configureFieldFilter(fn (Collection $fields, BaseBuilder $builderContext): Collection => $fields
            ->reject(fn (CustomField $field): bool => $field->code === 'cost'));

        $names = CustomFields::form()->forModel($this->post)->values()
            ->flatMap(fn (Component $section): array => infolistFieldNames($section))
            ->all();

        expect($names)->toContain('custom_fields.cost');
    });
});
