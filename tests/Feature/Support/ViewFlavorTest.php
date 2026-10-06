<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Exceptions;
use Relaticle\CustomFields\CustomFieldsServiceProvider;
use Relaticle\CustomFields\Enums\UiFlavor;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\Livewire\ManageFieldsTable;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Support\ViewFlavor;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

describe('flavor resolution', function (): void {
    it('forks exactly the surfaces the flavors are allowed to change', function (): void {
        expect(array_column(UiSurface::cases(), 'value'))->toBe([
            'relationship-configurator',
            'record-chips',
            'record-picker',
            'type-picker',
            'attribute-table',
        ]);
    });

    it('resolves a surface to its polished view under the polished flavor', function (string $key): void {
        config()->set('custom-fields.ui.flavor', 'polished');

        $surface = UiSurface::from($key);

        expect(ViewFlavor::flavor($surface))->toBe(UiFlavor::Polished)
            ->and(ViewFlavor::view($surface))->toBe('custom-fields::flavors.polished.'.$key);
    })->with([
        'relationship-configurator',
        'record-chips',
        'record-picker',
        'type-picker',
        'attribute-table',
    ]);

    it('falls back to polished when the config predates the flavor registry', function (): void {
        config()->set('custom-fields.ui', []);

        expect(ViewFlavor::flavor(UiSurface::AttributeTable))->toBe(UiFlavor::Polished);
    });

    it('drops every surface to the stock view under the global native flavor', function (): void {
        config()->set('custom-fields.ui.flavor', 'native');

        foreach (UiSurface::cases() as $surface) {
            expect(ViewFlavor::flavor($surface))->toBe(UiFlavor::Native)
                ->and(ViewFlavor::view($surface))->toBeNull();
        }
    });

    it('lets a per-surface override beat the global flavor in both directions', function (): void {
        config()->set('custom-fields.ui.flavor', 'native');
        config()->set('custom-fields.ui.flavor_overrides', ['attribute-table' => 'polished']);

        expect(ViewFlavor::view(UiSurface::AttributeTable))->toBe('custom-fields::flavors.polished.attribute-table')
            ->and(ViewFlavor::view(UiSurface::RecordChips))->toBeNull();

        config()->set('custom-fields.ui.flavor', 'polished');
        config()->set('custom-fields.ui.flavor_overrides', ['record-chips' => 'native']);

        expect(ViewFlavor::view(UiSurface::RecordChips))->toBeNull()
            ->and(ViewFlavor::view(UiSurface::AttributeTable))->toBe('custom-fields::flavors.polished.attribute-table');
    });

    it('rejects an unknown global flavor', function (): void {
        config()->set('custom-fields.ui.flavor', 'fancy');

        ViewFlavor::view(UiSurface::AttributeTable);
    })->throws(InvalidArgumentException::class, 'Unknown custom-fields UI flavor [fancy]');

    it('validates the global flavor even when an override covers the surface', function (): void {
        config()->set('custom-fields.ui.flavor', 'fancy');
        config()->set('custom-fields.ui.flavor_overrides', ['attribute-table' => 'polished']);

        ViewFlavor::view(UiSurface::AttributeTable);
    })->throws(InvalidArgumentException::class, 'Unknown custom-fields UI flavor [fancy]');

    it('rejects a bad flavor when the package boots, before any surface renders', function (): void {
        config()->set('custom-fields.ui.flavor', 'fancy');
        (function (): void {
            $this->isRunningInConsole = false;
        })->call(app());

        app()->register(CustomFieldsServiceProvider::class, force: true);
    })->throws(InvalidArgumentException::class, 'Unknown custom-fields UI flavor [fancy]');

    it('reports a bad flavor in the console so config:clear can recover from a cached one', function (): void {
        Exceptions::fake();
        config()->set('custom-fields.ui.flavor', 'fancy');

        app()->register(CustomFieldsServiceProvider::class, force: true);

        Exceptions::assertReported(fn (InvalidArgumentException $exception): bool => str_contains(
            $exception->getMessage(),
            'Unknown custom-fields UI flavor [fancy]',
        ));
    });

    it('rejects an unknown flavor in the override map', function (): void {
        config()->set('custom-fields.ui.flavor_overrides', ['attribute-table' => 'fancy']);

        ViewFlavor::view(UiSurface::AttributeTable);
    })->throws(InvalidArgumentException::class, 'Unknown custom-fields UI flavor [fancy]');

    it('rejects a surface key that does not fork', function (): void {
        config()->set('custom-fields.ui.flavor_overrides', ['field-form' => 'native']);

        ViewFlavor::view(UiSurface::AttributeTable);
    })->throws(InvalidArgumentException::class, 'Unknown custom-fields UI surface [field-form]');

    it('rejects an override map that is not a map', function (): void {
        config()->set('custom-fields.ui.flavor_overrides', 'native');

        ViewFlavor::view(UiSurface::AttributeTable);
    })->throws(InvalidArgumentException::class, 'must be an array of surface keys to flavors');
});

describe('attribute table rendering', function (): void {
    beforeEach(function (): void {
        $this->actingAs(User::factory()->create());

        CustomField::factory()->ofType('text')->create([
            'entity_type' => Post::class,
            'name' => 'Flavor smoke field',
        ]);
    });

    it('renders the attribute table through the polished view', function (): void {
        config()->set('custom-fields.ui.flavor', 'polished');

        livewire(ManageFieldsTable::class, ['entityType' => Post::class])
            ->assertSee('Flavor smoke field')
            ->assertSeeHtml('data-flavor="polished"');
    });

    it('renders the attribute table through the stock view in the native flavor', function (): void {
        config()->set('custom-fields.ui.flavor', 'native');

        livewire(ManageFieldsTable::class, ['entityType' => Post::class])
            ->assertSee('Flavor smoke field')
            ->assertDontSeeHtml('data-flavor=');
    });
});
