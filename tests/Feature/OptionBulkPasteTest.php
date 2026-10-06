<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Relaticle\CustomFields\Livewire\ManageCustomField;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldOption;
use Relaticle\CustomFields\Support\OptionNameParser;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

describe('the parser', function (): void {
    it('reads one name per line and drops the blank ones', function (): void {
        $parsed = OptionNameParser::parse("Discovery\n\n  Negotiation  \n\r\nClosed Won\n");

        expect($parsed['names'])->toBe(['Discovery', 'Negotiation', 'Closed Won'])
            ->and($parsed['duplicates'])->toBe(0)
            ->and($parsed['truncated'])->toBeFalse();
    });

    it('keeps a name once however the paste cased it', function (): void {
        $parsed = OptionNameParser::parse("Discovery\ndiscovery\nDISCOVERY");

        expect($parsed['names'])->toBe(['Discovery'])
            ->and($parsed['duplicates'])->toBe(2);
    });

    it('skips a name the editor already holds', function (): void {
        $parsed = OptionNameParser::parse("closed won\nNegotiation", ['Closed Won', '', null]);

        expect($parsed['names'])->toBe(['Negotiation'])
            ->and($parsed['duplicates'])->toBe(1);
    });

    it('reads no more names than the cap and says it stopped', function (): void {
        $lines = implode("\n", array_map(
            fn (int $index): string => 'Option '.$index,
            range(1, OptionNameParser::MAX_NAMES + 5),
        ));

        $parsed = OptionNameParser::parse($lines);

        expect($parsed['names'])->toHaveCount(OptionNameParser::MAX_NAMES)
            ->and($parsed['names'][0])->toBe('Option 1')
            ->and($parsed['truncated'])->toBeTrue();
    });

    it('reads an empty paste as nothing to add', function (): void {
        $parsed = OptionNameParser::parse(null);

        expect($parsed['names'])->toBe([])
            ->and($parsed['duplicates'])->toBe(0)
            ->and($parsed['truncated'])->toBeFalse();
    });
});

/**
 * @return array<int|string, array<string, mixed>>
 */
function pasteOptionsInto(CustomField $field, string $names): array
{
    return livewire(ManageCustomField::class, ['field' => $field])
        ->mountAction('edit')
        ->callAction(
            TestAction::make('pasteOptions')->schemaComponent('options'),
            ['names' => $names],
        )
        ->assertHasNoActionErrors()
        ->get('mountedActions.0.data.options');
}

describe('the options editor', function (): void {
    it('appends the pasted names as keyed rows', function (): void {
        $field = CustomField::factory()->ofType('select')->withOptions(['Discovery'])->create();

        $options = pasteOptionsInto($field, "Negotiation\nClosed Won");

        expect(array_column($options, 'name'))->toBe(['Discovery', 'Negotiation', 'Closed Won']);

        foreach (array_slice(array_keys($options), 1) as $key) {
            expect($key)->toMatch('/^[0-9a-f-]{36}$/');
        }
    });

    it('leaves out a name the editor already holds', function (): void {
        $field = CustomField::factory()->ofType('select')->withOptions(['Discovery'])->create();

        $options = livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->callAction(
                TestAction::make('pasteOptions')->schemaComponent('options'),
                ['names' => "discovery\nNegotiation"],
            )
            ->assertHasNoActionErrors()
            ->assertNotified(
                Notification::make()
                    ->success()
                    ->title('Paste a list of options')
                    ->body('1 added, 1 skipped as duplicates')
            )
            ->get('mountedActions.0.data.options');

        expect(array_column($options, 'name'))->toBe(['Discovery', 'Negotiation']);
    });

    it('drops the blank row the editor opens on', function (): void {
        $field = CustomField::factory()->ofType('select')->create();

        $page = livewire(ManageCustomField::class, ['field' => $field])->mountAction('edit');

        $blankKey = (string) Str::uuid();
        $page->set('mountedActions.0.data.options', [$blankKey => ['name' => null]]);

        $options = $page
            ->callAction(TestAction::make('pasteOptions')->schemaComponent('options'), ['names' => 'Discovery'])
            ->assertHasNoActionErrors()
            ->get('mountedActions.0.data.options');

        expect(array_column($options, 'name'))->toBe(['Discovery'])
            ->and($options)->not->toHaveKey($blankKey);
    });

    it('asks for something to paste', function (): void {
        $field = CustomField::factory()->ofType('select')->withOptions(['Discovery'])->create();

        livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->callAction(TestAction::make('pasteOptions')->schemaComponent('options'), ['names' => ''])
            ->assertHasActionErrors(['names' => 'required']);
    });

    it('stores the pasted rows through the repeater, in the order they landed', function (): void {
        $field = CustomField::factory()->ofType('select')->withOptions(['Discovery'])->create();

        livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->callAction(
                TestAction::make('pasteOptions')->schemaComponent('options'),
                ['names' => "Negotiation\nClosed Won"],
            )
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $options = $field->refresh()->options()->orderBy('sort_order')->get();

        expect($options->pluck('name')->all())->toBe(['Discovery', 'Negotiation', 'Closed Won'])
            ->and($options->pluck('sort_order')->all())->toBe([1, 2, 3])
            ->and($options->pluck('settings.category')->filter()->all())->toBe([]);
    });

    // The repeater hides its label, and a hint action rides in the label row, so the two
    // flavors are asserted on the rendered editor rather than on the schema alone.
    it('draws the paste action beside the hidden label in both flavors', function (string $flavor): void {
        config()->set('custom-fields.ui.flavor', $flavor);
        view()->share('errors', new ViewErrorBag);

        $field = CustomField::factory()->ofType('select')->withOptions(['Discovery'])->create();

        $component = livewire(ManageCustomField::class, ['field' => $field])
            ->mountAction('edit')
            ->instance();

        /** @var Repeater $repeater */
        $repeater = $component->{$component->getMountedActionSchemaName()}
            ->getFlatComponents(withHidden: true)['options'];

        expect((string) $repeater->toHtml())
            ->toContain('Paste a list')
            ->toContain('Add Option');
    })->with(['polished', 'native']);
});

describe('the create form', function (): void {
    it('stores the pasted rows on a field that does not exist yet, in the order they landed', function (): void {
        pasteIntoNewStageField("Discovery\nNegotiation\nClosed Won");

        $options = stageOptions();

        expect($options->pluck('name')->all())->toBe(['Discovery', 'Negotiation', 'Closed Won'])
            ->and($options->pluck('sort_order')->all())->toBe([0, 1, 2]);
    });

    it('stamps the tenant on every pasted row', function (): void {
        useTenantSchema(7);

        pasteIntoNewStageField("Discovery\nClosed Won");

        expect(stageOptions()->pluck('tenant_id')->all())->toBe([7, 7]);
    })->skip(
        fn (): bool => DB::connection()->getDriverName() === 'mysql',
        'MySQL commits DDL implicitly, so the added tenant columns would outlive the test transaction.',
    );
});

function pasteIntoNewStageField(string $names): void
{
    livewire(ManageCustomFieldSection::class, [
        'section' => sectionForEntity(User::class),
        'entityType' => User::class,
    ])
        ->mountAction('createField')
        ->set('mountedActions.0.data.type', 'select')
        ->callAction(TestAction::make('pasteOptions')->schemaComponent('options'), ['names' => $names])
        ->assertHasNoActionErrors()
        ->set('mountedActions.0.data.name', 'Stage')
        ->set('mountedActions.0.data.code', 'stage')
        ->callMountedAction()
        ->assertHasNoActionErrors();
}

/**
 * @return Collection<int, CustomFieldOption>
 */
function stageOptions(): Collection
{
    return CustomField::query()
        ->withoutGlobalScopes()
        ->where('code', 'stage')
        ->firstOrFail()
        ->options()
        ->withoutGlobalScopes()
        ->orderBy('sort_order')
        ->get();
}
