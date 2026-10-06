<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Filament\Management\Forms\Components\Visibility\ConditionOptions;
use Relaticle\CustomFields\Filament\Management\Forms\Components\Visibility\ConditionRow;
use Relaticle\CustomFields\Models\CustomFieldSection;

final class VisibilityComponent extends Component
{
    protected string $view = 'filament-schemas::components.grid';

    private ConditionOptions $conditionOptions;

    public function __construct()
    {
        $this->columnSpanFull();
    }

    /**
     * Register a resolver that constrains the conditional-visibility "depends on" field
     * picker. The resolver receives the entity type and the section the conditioned
     * field/section belongs to, and returns a query constraint closure, or null for no
     * scope. Register once (e.g. from a service provider).
     *
     * @param  ?Closure(string $entityType, ?CustomFieldSection $section): ?Closure  $resolver
     */
    public static function resolveAvailableFieldsScopeUsing(?Closure $resolver): void
    {
        ConditionOptions::resolveAvailableFieldsScopeUsing($resolver);
    }

    public static function make(?CustomFieldSection $scopeSection = null): static
    {
        return self::conditionedBy(new ConditionOptions(scopeSection: $scopeSection));
    }

    public static function makeForSection(string $entityType, ?CustomFieldSection $scopeSection = null): static
    {
        return self::conditionedBy(new ConditionOptions(
            forSection: true,
            sectionEntityType: $entityType,
            scopeSection: $scopeSection,
        ));
    }

    /**
     * The row schema reads the entity type and the scope section per render, so it can only be
     * built once they are known: building it in the constructor would freeze an unscoped picker.
     */
    private static function conditionedBy(ConditionOptions $options): static
    {
        $instance = new self;
        $instance->conditionOptions = $options;
        $instance->schema([$instance->buildFieldset()]);

        return $instance;
    }

    private function buildFieldset(): Fieldset
    {
        return Fieldset::make(__('custom-fields::custom-fields.visibility.heading'))->schema([
            Select::make('settings.visibility.mode')
                ->label(__('custom-fields::custom-fields.visibility.mode'))
                ->options(VisibilityMode::class)
                ->default(VisibilityMode::ALWAYS_VISIBLE)
                ->required()
                ->afterStateHydrated(function (
                    Select $component,
                    mixed $state
                ): void {
                    $component->state($state ?? VisibilityMode::ALWAYS_VISIBLE);
                })
                ->live(),

            Select::make('settings.visibility.logic')
                ->label(__('custom-fields::custom-fields.visibility.logic'))
                ->options(VisibilityLogic::class)
                ->default(VisibilityLogic::ALL)
                ->required()
                ->visible(fn (Get $get): bool => $this->modeRequiresConditions($get)),

            Repeater::make('settings.visibility.conditions')
                ->label(__('custom-fields::custom-fields.visibility.conditions'))
                ->schema((new ConditionRow($this->conditionOptions))->components())
                ->visible(fn (Get $get): bool => $this->modeRequiresConditions($get))
                ->defaultItems(1)
                ->minItems(1)
                ->maxItems(10)
                ->columnSpanFull()
                ->reorderable(false)
                ->columns(12),
        ]);
    }

    private function modeRequiresConditions(Get $get): bool
    {
        $mode = $get('settings.visibility.mode');

        return $mode instanceof VisibilityMode && $mode->requiresConditions();
    }
}
