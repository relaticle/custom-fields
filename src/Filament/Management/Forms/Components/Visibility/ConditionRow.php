<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components\Visibility;

use Exception;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Services\ModelAttributeDiscoveryService;

final readonly class ConditionRow
{
    public function __construct(private ConditionOptions $options) {}

    /**
     * @return array<int, Component>
     *
     * @throws Exception
     */
    public function components(): array
    {
        $schema = [];

        $schema[] = Select::make('source')
            ->label(__('custom-fields::custom-fields.visibility.source'))
            ->options(fn (Get $get): array => $this->options->getAvailableSourceOptions($get))
            ->default(ConditionSource::CustomField->value)
            ->required()
            ->live()
            ->afterStateUpdated(fn (Set $set) => $this->resetConditionValues(null, $set))
            // Show the source picker only when more than the default CustomField source is available
            // (model-attribute flag on, or the entity has configured relation paths). Decided per-render
            // via $get so it works in Livewire action contexts where the entity is not known at build time.
            // When hidden, the default keeps source = custom_field.
            ->visible(fn (Get $get): bool => count($this->options->getAvailableSourceOptions($get)) > 1)
            ->columnSpan(3);

        $schema[] = Select::make('field_code')
            ->label(__('custom-fields::custom-fields.visibility.field'))
            ->options(fn (Get $get): array => $this->options->getAvailableFields($get))
            ->required()
            ->live()
            ->afterStateUpdated(fn (Get $get, Set $set) => $this->resetValuesAndOperator($get, $set))
            ->columnSpan(3);

        $schema[] = Select::make('operator')
            ->label(__('custom-fields::custom-fields.visibility.operator'))
            ->options(fn (Get $get): array => $this->options->getCompatibleOperators($get))
            ->required()
            ->live()
            ->afterStateUpdated(fn (Get $get, Set $set) => $this->clearValuesForOperatorChange($get, $set))
            ->columnSpan(2);

        $schema = [...$schema, ...$this->getValueInputComponents(4)];

        $schema[] = Hidden::make('value')->default(null);

        return $schema;
    }

    /**
     * @return array<int, Component>
     *
     * @throws Exception
     */
    private function getValueInputComponents(int $columnSpan = 5): array
    {
        return [
            Select::make('single_value')
                ->label(__('custom-fields::custom-fields.visibility.value'))
                ->live()
                ->searchable()
                ->options(fn (Get $get): array => $this->options->getFieldOptions($get))
                ->visible(fn (Get $get): bool => $this->shouldShowSingleSelect($get))
                ->placeholder(fn (Get $get): string => $this->getPlaceholder($get))
                // Scalar value inputs must ignore array values (relation/multi-choice conditions), else hydrating
                // an array into a single-select throws "Array to string conversion".
                ->afterStateHydrated(fn (Select $component, Get $get): Select => $component->state(is_array($get('value')) ? null : $get('value')))
                ->afterStateUpdated(fn (mixed $state, Set $set): mixed => $set('value', $state))
                ->columnSpan($columnSpan),

            Select::make('multiple_values')
                ->label(__('custom-fields::custom-fields.visibility.value'))
                ->live()
                ->searchable()
                ->multiple()
                ->options(fn (Get $get): array => $this->options->getFieldOptions($get))
                ->visible(fn (Get $get): bool => $this->shouldShowMultipleSelect($get))
                ->placeholder(fn (Get $get): string => $this->getPlaceholder($get))
                ->afterStateHydrated(fn (Select $component, Get $get): Select => $component->state(value($get('value')) ? (array) $get('value') : []))
                ->afterStateUpdated(fn (array $state, Set $set): mixed => $set('value', $state))
                ->columnSpan($columnSpan),

            Toggle::make('boolean_value')
                ->inline(false)
                ->label(__('custom-fields::custom-fields.visibility.value'))
                ->visible(fn (Get $get): bool => $this->shouldShowToggle($get))
                ->afterStateHydrated(fn (Toggle $component, Get $get): Toggle => $component->state(is_array($get('value')) ? false : $get('value')))
                ->afterStateUpdated(fn (bool $state, Set $set): mixed => $set('value', $state))
                ->columnSpan($columnSpan),

            TextInput::make('text_value')
                ->label(__('custom-fields::custom-fields.visibility.value'))
                ->placeholder(fn (Get $get): string => $this->getPlaceholder($get))
                ->visible(fn (Get $get): bool => $this->shouldShowTextInput($get))
                ->afterStateHydrated(fn (TextInput $component, Get $get): TextInput => $component->state(is_array($get('value')) ? '' : ($get('value') ?? '')))
                ->afterStateUpdated(fn (mixed $state, Set $set): mixed => $set('value', $state))
                ->columnSpan($columnSpan),

            Select::make('relation_values')
                ->label(__('custom-fields::custom-fields.visibility.value'))
                ->multiple()
                ->searchable()
                ->options(fn (Get $get): array => $this->options->getRelationValueOptions($get))
                ->visible(fn (Get $get): bool => $this->options->isRelationAttributeSource($get) && $this->operatorRequiresValue($get))
                ->afterStateHydrated(fn (Select $component, Get $get): Select => $component->state(value($get('value')) ? (array) $get('value') : []))
                ->afterStateUpdated(fn (mixed $state, Set $set): mixed => $set('value', $state))
                ->columnSpan($columnSpan),
        ];
    }

    private function shouldShowSingleSelect(Get $get): bool
    {
        if (! $this->operatorRequiresValue($get)) {
            return false;
        }

        if ($this->options->isModelAttributeSource($get)) {
            return false;
        }

        if ($this->options->isRelationAttributeSource($get)) {
            return false;
        }

        $fieldData = $this->options->getFieldTypeData($get);
        if ($fieldData === null) {
            return false;
        }

        if (! $fieldData->dataType->isChoiceField()) {
            return false;
        }

        $operator = $get('operator');
        if (! $fieldData->dataType->isMultiChoiceField()) {
            return true;
        }

        return ! $this->isContainsOperator($operator);
    }

    private function shouldShowMultipleSelect(Get $get): bool
    {
        if (! $this->operatorRequiresValue($get)) {
            return false;
        }

        if ($this->options->isModelAttributeSource($get)) {
            return false;
        }

        if ($this->options->isRelationAttributeSource($get)) {
            return false;
        }

        $fieldData = $this->options->getFieldTypeData($get);
        if ($fieldData === null) {
            return false;
        }

        return $fieldData->dataType->isMultiChoiceField() &&
               $this->isContainsOperator($get('operator'));
    }

    private function shouldShowToggle(Get $get): bool
    {
        if (! $this->operatorRequiresValue($get)) {
            return false;
        }

        if ($this->options->isRelationAttributeSource($get)) {
            return false;
        }

        if ($this->options->isModelAttributeSource($get)) {
            $entityType = $this->options->getEntityType($get);
            if (blank($entityType)) {
                return false;
            }

            $fieldCode = $get('field_code');
            if (blank($fieldCode)) {
                return false;
            }

            $dataType = app(ModelAttributeDiscoveryService::class)->getAttributeDataType($entityType, $fieldCode);

            return $dataType === FieldDataType::BOOLEAN;
        }

        $fieldData = $this->options->getFieldTypeData($get);

        return $fieldData && $fieldData->dataType === FieldDataType::BOOLEAN;
    }

    private function shouldShowTextInput(Get $get): bool
    {
        if (! $this->operatorRequiresValue($get)) {
            return false;
        }

        if ($this->options->isRelationAttributeSource($get)) {
            return false;
        }

        if ($this->options->isModelAttributeSource($get)) {
            return ! $this->shouldShowToggle($get);
        }

        $fieldData = $this->options->getFieldTypeData($get);
        if ($fieldData === null) {
            return true;
        }

        return ! $fieldData->dataType->isChoiceField() &&
               $fieldData->dataType !== FieldDataType::BOOLEAN;
    }

    private function getPlaceholder(Get $get): string
    {
        if (blank($get('field_code'))) {
            return 'Select a field first';
        }

        if (blank($get('operator'))) {
            return 'Select an operator first';
        }

        if ($this->options->isModelAttributeSource($get)) {
            return 'Enter comparison value';
        }

        $fieldData = $this->options->getFieldTypeData($get);
        if ($fieldData === null) {
            return 'Enter comparison value';
        }

        if ($fieldData->dataType->isChoiceField()) {
            return $this->shouldShowMultipleSelect($get)
                ? 'Select one or more options'
                : 'Select an option';
        }

        return match ($fieldData->dataType) {
            FieldDataType::NUMERIC => 'Enter a number',
            FieldDataType::DATE, FieldDataType::DATE_TIME => 'Enter a date (YYYY-MM-DD)',
            FieldDataType::BOOLEAN => 'Toggle value',
            default => 'Enter comparison value',
        };
    }

    private function operatorRequiresValue(Get $get): bool
    {
        $operator = $get('operator');
        if (blank($operator)) {
            return true;
        }

        return rescue(
            fn () => VisibilityOperator::from($operator)->requiresValue(),
            true
        );
    }

    private function resetConditionValues(?Get $get, Set $set): void
    {
        $this->clearAllValueFields($set);
        $set('field_code', null);

        if ($get instanceof Get) {
            $set('operator', array_key_first($this->options->getCompatibleOperators($get)));
        }
    }

    private function resetValuesAndOperator(Get $get, Set $set): void
    {
        $this->clearAllValueFields($set);
        $set('operator', array_key_first($this->options->getCompatibleOperators($get)));
    }

    private function clearValuesForOperatorChange(Get $get, Set $set): void
    {
        // Switching between value-taking operators (e.g. Is in -> Is not in) must keep the value;
        // clearing unconditionally here previously wiped a saved condition's value on any operator
        // change, silently turning it into an "is in / is not in nothing" match.
        if ($this->operatorRequiresValue($get)) {
            return;
        }

        $this->clearAllValueFields($set);
    }

    private function clearAllValueFields(Set $set): void
    {
        $set('value', null);
        $set('text_value', null);
        $set('boolean_value', false);
        $set('single_value', null);
        $set('multiple_values', []);
        $set('relation_values', []);
    }

    private function isContainsOperator(?string $operator): bool
    {
        return in_array($operator, [
            VisibilityOperator::CONTAINS->value,
            VisibilityOperator::NOT_CONTAINS->value,
        ], true);
    }
}
