<?php

declare(strict_types=1);

use Filament\Forms\Components\Field;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Exceptions;
use Livewire\Features\SupportTesting\Testable;
use Relaticle\CustomFields\CustomFieldsServiceProvider;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\FieldFormPresentation;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageFieldsTable;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Support\FieldFormConfiguration;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

/**
 * @return array<int, string>
 */
function visibleSettingInputs(Testable $component): array
{
    $livewire = $component->instance();
    $schema = $livewire->getSchema($livewire->getMountedActionSchemaName());

    $names = [];

    foreach ($schema?->getFlatComponents() ?? [] as $child) {
        if (! $child instanceof Field || ! $child->isVisible()) {
            continue;
        }

        // settings.visibility belongs to the conditional visibility component, not to the
        // optional settings a host narrows.
        if (str_starts_with($child->getName(), 'settings.') && ! str_starts_with($child->getName(), 'settings.visibility.')) {
            $names[] = $child->getName();
        }
    }

    return $names;
}

function mountCreateField(string $type): Testable
{
    return livewire(ManageFieldsTable::class, ['entityType' => Post::class])
        ->mountAction('createField')
        ->set('mountedActions.0.data.type', $type);
}

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

describe('presentation', function (): void {
    it('opens as the slide over panel until a host asks otherwise', function (): void {
        expect(FieldFormConfiguration::presentation())->toBe(FieldFormPresentation::SlideOver)
            ->and(FieldFormConfiguration::isSlideOver())->toBeTrue()
            ->and(FieldFormConfiguration::width())->toBe(Width::ScreenLarge);
    });

    it('opens as a narrower dialog when a host asks for a modal', function (): void {
        config()->set('custom-fields.field_form.presentation', 'modal');

        expect(FieldFormConfiguration::presentation())->toBe(FieldFormPresentation::Modal)
            ->and(FieldFormConfiguration::isSlideOver())->toBeFalse()
            ->and(FieldFormConfiguration::width())->toBe(Width::FourExtraLarge);
    });

    it('carries the presentation onto the action a user clicks', function (string $presentation, bool $isSlideOver): void {
        config()->set('custom-fields.field_form.presentation', $presentation);

        $action = livewire(ManageFieldsTable::class, ['entityType' => Post::class])
            ->instance()
            ->createFieldAction();

        expect($action->isModalSlideOver())->toBe($isSlideOver);
    })->with([
        ['slide_over', true],
        ['modal', false],
    ]);

    it('falls back to the slide over when the config predates the setting', function (): void {
        config()->set('custom-fields.field_form', []);

        expect(FieldFormConfiguration::presentation())->toBe(FieldFormPresentation::SlideOver);
    });

    it('rejects an unknown presentation', function (): void {
        config()->set('custom-fields.field_form.presentation', 'popover');

        FieldFormConfiguration::presentation();
    })->throws(InvalidArgumentException::class, 'Unknown custom-fields field form presentation [popover]');
});

describe('the settings the form offers', function (): void {
    it('offers every setting a host does not narrow', function (): void {
        expect(FieldFormConfiguration::offers('encrypted'))->toBeTrue()
            ->and(visibleSettingInputs(mountCreateField('text')))
            ->toContain('settings.visible_in_list', 'settings.visible_in_view');
    });

    it('offers only the settings a host names', function (): void {
        config()->set('custom-fields.field_form.settings', ['searchable']);

        expect(visibleSettingInputs(mountCreateField('text')))->toBe(['settings.searchable'])
            ->and(FieldFormConfiguration::offers('encrypted'))->toBeFalse();
    });

    it('drops the settings group a narrowed form no longer fills', function (): void {
        config()->set('custom-fields.field_form.settings', ['allow_multiple']);

        mountCreateField('text')->assertDontSee('Settings');
    });

    it('keeps option colors on when the form stops asking about them', function (): void {
        CustomFieldSection::factory()->forEntityType(Post::class)->create();

        config(['custom-fields.features' => FeatureConfigurator::configure()->enable(
            CustomFieldsFeature::FIELD_OPTION_COLORS,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        )]);
        config()->set('custom-fields.field_form.settings', ['allow_multiple']);

        livewire(ManageFieldsTable::class, ['entityType' => Post::class])
            ->callAction('createField', [
                'name' => 'Deal health',
                'code' => 'deal_health',
                'type' => 'select',
                'entity_type' => Post::class,
                'options' => [['name' => 'At risk']],
            ])
            ->assertHasNoActionErrors();

        $field = CustomField::query()->withoutGlobalScopes()->where('code', 'deal_health')->sole();

        expect($field->settings->enable_option_colors)->toBeTrue();
    });

    it('leaves the color flag alone on a type that has no options', function (): void {
        CustomFieldSection::factory()->forEntityType(Post::class)->create();

        config(['custom-fields.features' => FeatureConfigurator::configure()->enable(
            CustomFieldsFeature::FIELD_OPTION_COLORS,
            CustomFieldsFeature::SYSTEM_SECTIONS,
        )]);
        config()->set('custom-fields.field_form.settings', ['allow_multiple']);

        livewire(ManageFieldsTable::class, ['entityType' => Post::class])
            ->callAction('createField', [
                'name' => 'Notes',
                'code' => 'notes',
                'type' => 'textarea',
                'entity_type' => Post::class,
            ])
            ->assertHasNoActionErrors();

        $field = CustomField::query()->withoutGlobalScopes()->where('code', 'notes')->sole();

        expect($field->settings->enable_option_colors)->toBeFalse();
    });

    it('keeps a setting the form never showed through an edit', function (): void {
        $field = CustomField::factory()->ofType('text')->create([
            'entity_type' => Post::class,
            'code' => 'tax_id',
            'name' => 'Tax id',
            'settings' => new CustomFieldSettingsData(
                encrypted: true,
                unique_per_entity_type: true,
            ),
        ]);

        config()->set('custom-fields.field_form.settings', ['allow_multiple']);

        livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->set('mountedActions.0.data.name', 'Tax number')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $field->refresh();

        expect($field->name)->toBe('Tax number')
            ->and($field->settings->encrypted)->toBeTrue()
            ->and($field->settings->unique_per_entity_type)->toBeTrue();
    });

    it('rejects a setting key the form has no component for', function (): void {
        config()->set('custom-fields.field_form.settings', ['colour']);

        FieldFormConfiguration::offers('searchable');
    })->throws(InvalidArgumentException::class, 'Unknown custom-fields field form setting [colour]');

    it('rejects a settings value that is not a list', function (): void {
        config()->set('custom-fields.field_form.settings', 'searchable');

        FieldFormConfiguration::offers('searchable');
    })->throws(InvalidArgumentException::class, 'must be null or a list of setting keys');
});

describe('boot', function (): void {
    it('rejects a bad presentation when the package boots, before any form opens', function (): void {
        config()->set('custom-fields.field_form.presentation', 'popover');
        (function (): void {
            $this->isRunningInConsole = false;
        })->call(app());

        app()->register(CustomFieldsServiceProvider::class, force: true);
    })->throws(InvalidArgumentException::class, 'Unknown custom-fields field form presentation [popover]');

    it('reports a bad setting key in the console so config:clear can recover from a cached one', function (): void {
        Exceptions::fake();
        config()->set('custom-fields.field_form.settings', ['colour']);

        app()->register(CustomFieldsServiceProvider::class, force: true);

        Exceptions::assertReported(fn (InvalidArgumentException $exception): bool => str_contains(
            $exception->getMessage(),
            'Unknown custom-fields field form setting [colour]',
        ));
    });
});
