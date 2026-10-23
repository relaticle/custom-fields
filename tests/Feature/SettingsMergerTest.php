<?php

declare(strict_types=1);

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Filament\Management\Schemas\SectionForm;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Livewire\ManageFieldsTable;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Support\SettingsMerger;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

afterEach(function (): void {
    FieldForm::flushSchemaExtensions();
    SectionForm::flushSchemaExtensions();
});

mutates(SettingsMerger::class, ManageCustomField::class, ManageCustomFieldSection::class, ManageFieldsTable::class);

describe('merge semantics', function (): void {
    it('preserves a stored key the submission does not mention', function (): void {
        $merged = SettingsMerger::merge(
            ['additional' => ['currency_code' => 'USD', 'hidden_in_panels' => ['portal']]],
            ['additional' => ['currency_code' => 'EUR']],
        );

        expect($merged)->toBe(['additional' => ['currency_code' => 'EUR', 'hidden_in_panels' => ['portal']]]);
    });

    it('lets a submitted empty array clear a stored list', function (): void {
        $merged = SettingsMerger::merge(
            ['additional' => ['hidden_in_panels' => ['portal']]],
            ['additional' => ['hidden_in_panels' => []]],
        );

        expect($merged)->toBe(['additional' => ['hidden_in_panels' => []]]);
    });

    it('replaces a list wholesale instead of merging by index', function (): void {
        $merged = SettingsMerger::merge(
            ['visibility' => ['conditions' => [['field_code' => 'a'], ['field_code' => 'b']]]],
            ['visibility' => ['conditions' => [['field_code' => 'c']]]],
        );

        expect($merged)->toBe(['visibility' => ['conditions' => [['field_code' => 'c']]]]);
    });

    it('lets a submitted null overwrite a stored scalar', function (): void {
        expect(SettingsMerger::merge(['description' => 'old'], ['description' => null]))
            ->toBe(['description' => null]);
    });

    it('adds a submitted key the store did not have', function (): void {
        expect(SettingsMerger::merge(['visible_in_list' => true], ['searchable' => true]))
            ->toBe(['visible_in_list' => true, 'searchable' => true]);
    });
});

describe('section edit', function (): void {
    it('keeps an extra bag key that has no form component', function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(CustomFieldsFeature::SYSTEM_SECTIONS, CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY));

        $section = CustomFieldSection::factory()->forEntityType(Post::class)->create([
            'code' => 'internal',
            'settings' => ['extra' => ['hidden_in_panels' => ['portal']]],
        ]);

        livewire(ManageCustomFieldSection::class, ['section' => $section, 'entityType' => Post::class])
            ->callAction('edit', ['name' => 'Renamed', 'code' => 'internal', 'settings' => ['visibility' => ['mode' => 'always_visible']]])
            ->assertHasNoFormErrors();

        expect($section->refresh()->settings->extra)->toBe(['hidden_in_panels' => ['portal']]);
    });

    it('keeps a second extra key when a rendered extension key is edited', function (): void {
        $section = CustomFieldSection::factory()->forEntityType(Post::class)->create([
            'code' => 'internal',
            'settings' => ['extra' => ['render_as_tab' => true, 'hidden_in_panels' => ['portal']]],
        ]);

        SectionForm::extendSchemaUsing(fn (array $schema): array => [
            ...$schema,
            Toggle::make('settings.extra.render_as_tab'),
        ]);

        livewire(ManageCustomFieldSection::class, ['section' => $section, 'entityType' => Post::class])
            ->callAction('edit', ['name' => 'Renamed', 'code' => 'internal', 'settings' => ['extra' => ['render_as_tab' => false]]])
            ->assertHasNoFormErrors();

        expect($section->refresh()->settings->extra)->toBe(['render_as_tab' => false, 'hidden_in_panels' => ['portal']]);
    });
});

describe('field edit', function (): void {
    it('replaces an extension list and preserves other settings in flat management', function (array $panels): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()->disable(CustomFieldsFeature::SYSTEM_SECTIONS));

        $field = CustomField::factory()->ofType('currency')->create([
            'entity_type' => Post::class,
            'name' => 'Cost',
            'code' => 'cost',
            'settings' => ['additional' => [
                'currency_code' => 'USD',
                'hidden_in_panels' => ['portal', 'admin'],
                'integration_key' => 'accounting',
            ]],
        ]);

        FieldForm::extendSchemaUsing(fn (array $schema): array => [
            ...$schema,
            CheckboxList::make('settings.additional.hidden_in_panels')
                ->options(['portal' => 'Portal', 'admin' => 'Admin']),
        ]);

        livewire(ManageFieldsTable::class, ['entityType' => Post::class])
            ->mountAction('editField', arguments: ['fieldId' => $field->id])
            ->setActionData([
                'name' => 'Annual Cost',
                'settings' => ['additional' => ['currency_code' => 'EUR']],
            ])
            ->set('mountedActions.0.data.settings.additional.hidden_in_panels', $panels)
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($field->refresh()->name)->toBe('Annual Cost')
            ->and($field->settings->additional)
            ->toHaveKey('currency_code', 'EUR')
            ->toHaveKey('hidden_in_panels', $panels)
            ->toHaveKey('integration_key', 'accounting');
    })->with(['shortened' => [['portal']], 'cleared' => [[]]]);

    it('clears conditions when the edit switches the field to always visible', function (): void {
        $section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['code' => 'main']);
        CustomField::factory()->create([
            'custom_field_section_id' => $section->id,
            'entity_type' => Post::class,
            'name' => 'Headline',
            'code' => 'headline',
            'type' => 'text',
        ]);

        $field = CustomField::factory()
            ->conditionallyVisible('headline', VisibilityOperator::EQUALS->value, 'Sale')
            ->create([
                'custom_field_section_id' => $section->id,
                'entity_type' => Post::class,
                'name' => 'Promo',
                'code' => 'promo',
                'type' => 'text',
            ]);

        livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->setActionData(['settings' => ['visibility' => ['mode' => VisibilityMode::ALWAYS_VISIBLE->value]]])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        expect($field->refresh()->settings->visibility->mode)->toBe(VisibilityMode::ALWAYS_VISIBLE)
            ->and($field->settings->visibility->conditions)->toBeNull();
    });

    it('keeps an additional key that has no form component when a type setting is edited', function (): void {
        $section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['code' => 'main']);
        $field = CustomField::factory()->ofType('currency')->create([
            'custom_field_section_id' => $section->id,
            'entity_type' => Post::class,
            'name' => 'Cost',
            'code' => 'cost',
            'settings' => ['additional' => ['currency_code' => 'USD', 'hidden_in_panels' => ['portal']]],
        ]);

        livewire(ManageCustomField::class, ['field' => $field])
            ->callAction('edit', data: [
                'name' => 'Cost',
                'code' => 'cost',
                'type' => 'currency',
                'settings' => ['additional' => ['currency_code' => 'EUR']],
            ])
            ->assertHasNoActionErrors();

        expect($field->refresh()->settings->additional)
            ->toHaveKey('currency_code', 'EUR')
            ->toHaveKey('hidden_in_panels', ['portal']);
    });
});
