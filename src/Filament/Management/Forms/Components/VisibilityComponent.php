<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Exception;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\ModelAttributeDiscoveryService;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;

/**
 * ABOUTME: Visibility component for configuring field visibility conditions.
 * ABOUTME: Provides dynamic form inputs based on field types and operators.
 */
final class VisibilityComponent extends Component
{
    protected string $view = 'filament-schemas::components.grid';

    private string $statePrefix = 'settings.visibility';

    private bool $forSection = false;

    private ?string $sectionEntityType = null;

    public function __construct()
    {
        $this->schema([$this->buildFieldset()]);
        $this->columnSpanFull();
    }

    public static function make(): static
    {
        return new self;
    }

    public static function makeForSection(string $entityType): static
    {
        $instance = new self;
        $instance->forSection = true;
        $instance->sectionEntityType = $entityType;
        $instance->schema([$instance->buildFieldset()]);

        return $instance;
    }

    private function buildFieldset(): Fieldset
    {
        return Fieldset::make('Conditional Visibility')->schema([
            Select::make("{$this->statePrefix}.mode")
                ->label('Visibility')
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

            Select::make("{$this->statePrefix}.logic")
                ->label('Condition Logic')
                ->options(VisibilityLogic::class)
                ->default(VisibilityLogic::ALL)
                ->required()
                ->visible(fn (Get $get): bool => $this->modeRequiresConditions($get)),

            Repeater::make("{$this->statePrefix}.conditions")
                ->label('Conditions')
                ->schema($this->buildConditionSchema())
                ->visible(fn (Get $get): bool => $this->modeRequiresConditions($get))
                ->defaultItems(1)
                ->minItems(1)
                ->maxItems(10)
                ->columnSpanFull()
                ->reorderable(false)
                ->columns(12),
        ]);
    }

    /**
     * @return array<int, Component>
     *
     * @throws Exception
     */
    private function buildConditionSchema(): array
    {
        $modelAttributeConditionsEnabled = FeatureManager::isEnabled(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS);

        return [
            // Source selector: only shown when model attribute conditions feature is enabled
            Select::make('source')
                ->label('Source')
                ->options(ConditionSource::class)
                ->default(ConditionSource::CustomField)
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => $this->resetConditionValues($get, $set))
                ->columnSpan(2)
                ->visible(fn (): bool => $modelAttributeConditionsEnabled),

            Select::make('field_code')
                ->label('Field')
                ->options(fn (Get $get): array => $this->getAvailableFields($get))
                ->required()
                ->live()
                ->afterStateUpdated(fn (Get $get, Set $set) => $this->resetConditionValues($get, $set))
                ->columnSpan(fn (): int => $modelAttributeConditionsEnabled ? 3 : 4),

            Select::make('operator')
                ->label('Operator')
                ->options(fn (Get $get): array => $this->getCompatibleOperators($get))
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $this->clearAllValueFields($set))
                ->columnSpan(fn (): int => $modelAttributeConditionsEnabled ? 2 : 3),

            ...$this->getValueInputComponents(),

            Hidden::make('value')->default(null),
        ];
    }

    /**
     * @return array<int, Component>
     *
     * @throws Exception
     */
    private function getValueInputComponents(): array
    {
        return [
            // Single select for choice fields
            Select::make('single_value')
                ->label('Value')
                ->live()
                ->searchable()
                ->options(fn (Get $get): array => $this->getFieldOptions($get))
                ->visible(fn (Get $get): bool => $this->shouldShowSingleSelect($get))
                ->placeholder(fn (Get $get): string => $this->getPlaceholder($get))
                ->afterStateHydrated(fn (Select $component, Get $get): Select => $component->state($get('value')))
                ->afterStateUpdated(fn (mixed $state, Set $set): mixed => $set('value', $state))
                ->columnSpan(5),

            // Multiple select for multi-choice fields
            Select::make('multiple_values')
                ->label('Value')
                ->live()
                ->searchable()
                ->multiple()
                ->options(fn (Get $get): array => $this->getFieldOptions($get))
                ->visible(fn (Get $get): bool => $this->shouldShowMultipleSelect($get))
                ->placeholder(fn (Get $get): string => $this->getPlaceholder($get))
                ->afterStateHydrated(fn (Select $component, Get $get): Select => $component->state(value($get('value')) ? (array) $get('value') : []))
                ->afterStateUpdated(fn (array $state, Set $set): mixed => $set('value', $state))
                ->columnSpan(5),

            // Toggle for boolean fields
            Toggle::make('boolean_value')
                ->inline(false)
                ->label('Value')
                ->visible(fn (Get $get): bool => $this->shouldShowToggle($get))
                ->afterStateHydrated(fn (Toggle $component, Get $get): Toggle => $component->state($get('value')))
                ->afterStateUpdated(fn (bool $state, Set $set): mixed => $set('value', $state))
                ->columnSpan(5),

            // Text input for other fields
            TextInput::make('text_value')
                ->label('Value')
                ->placeholder(fn (Get $get): string => $this->getPlaceholder($get))
                ->visible(fn (Get $get): bool => $this->shouldShowTextInput($get))
                ->afterStateHydrated(fn (TextInput $component, Get $get): TextInput => $component->state($get('value') ?? ''))
                ->afterStateUpdated(fn (mixed $state, Set $set): mixed => $set('value', $state))
                ->columnSpan(5),
        ];
    }

    private function getConditionSource(Get $get): ConditionSource
    {
        $source = $get('source');

        if ($source instanceof ConditionSource) {
            return $source;
        }

        if (is_string($source)) {
            return ConditionSource::tryFrom($source) ?? ConditionSource::CustomField;
        }

        return ConditionSource::CustomField;
    }

    private function isModelAttributeSource(Get $get): bool
    {
        return $this->getConditionSource($get) === ConditionSource::ModelAttribute;
    }

    private function shouldShowSingleSelect(Get $get): bool
    {
        if (! $this->operatorRequiresValue($get)) {
            return false;
        }

        // Model attributes never show select (no predefined options)
        if ($this->isModelAttributeSource($get)) {
            return false;
        }

        $fieldData = $this->getFieldTypeData($get);
        if ($fieldData === null) {
            return false;
        }

        if (! $fieldData->dataType->isChoiceField()) {
            return false;
        }

        $operator = $get('operator');

        return ! ($fieldData->dataType->isMultiChoiceField() && $this->isContainsOperator($operator));
    }

    private function shouldShowMultipleSelect(Get $get): bool
    {
        if (! $this->operatorRequiresValue($get)) {
            return false;
        }

        // Model attributes never show multi-select
        if ($this->isModelAttributeSource($get)) {
            return false;
        }

        $fieldData = $this->getFieldTypeData($get);
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

        if ($this->isModelAttributeSource($get)) {
            $dataType = $this->getModelAttributeDataType($get);

            return $dataType === FieldDataType::BOOLEAN;
        }

        $fieldData = $this->getFieldTypeData($get);

        return $fieldData && $fieldData->dataType === FieldDataType::BOOLEAN;
    }

    private function shouldShowTextInput(Get $get): bool
    {
        if (! $this->operatorRequiresValue($get)) {
            return false;
        }

        if ($this->isModelAttributeSource($get)) {
            $dataType = $this->getModelAttributeDataType($get);

            // Show text input for all model attribute types except boolean
            return $dataType !== FieldDataType::BOOLEAN;
        }

        $fieldData = $this->getFieldTypeData($get);
        if ($fieldData === null) {
            return true; // Default to text input
        }

        return ! $fieldData->dataType->isChoiceField() &&
               $fieldData->dataType !== FieldDataType::BOOLEAN;
    }

    /**
     * @return array<string, string>
     */
    private function getFieldOptions(Get $get): array
    {
        $fieldCode = $get('field_code');
        if (blank($fieldCode)) {
            return [];
        }

        // Model attributes don't have predefined options
        if ($this->isModelAttributeSource($get)) {
            return [];
        }

        $entityType = $this->getEntityType($get);
        if (blank($entityType)) {
            return [];
        }

        return rescue(function () use ($fieldCode, $entityType) {
            return app(BackendVisibilityService::class)
                ->getFieldOptions($fieldCode, $entityType);
        }, []);
    }

    private function getPlaceholder(Get $get): string
    {
        if (blank($get('field_code'))) {
            return 'Select a field first';
        }

        if (blank($get('operator'))) {
            return 'Select an operator first';
        }

        if ($this->isModelAttributeSource($get)) {
            $dataType = $this->getModelAttributeDataType($get);

            return match ($dataType) {
                FieldDataType::NUMERIC, FieldDataType::FLOAT => 'Enter a number',
                FieldDataType::DATE, FieldDataType::DATE_TIME => 'Enter a date (YYYY-MM-DD)',
                FieldDataType::BOOLEAN => 'Toggle value',
                default => 'Enter comparison value',
            };
        }

        $fieldData = $this->getFieldTypeData($get);
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

    private function modeRequiresConditions(Get $get): bool
    {
        $mode = $get("{$this->statePrefix}.mode");

        return $mode instanceof VisibilityMode && $mode->requiresConditions();
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

    /**
     * @return array<string, string>
     */
    private function getAvailableFields(Get $get): array
    {
        $entityType = $this->getEntityType($get);
        if (blank($entityType)) {
            return [];
        }

        if ($this->isModelAttributeSource($get)) {
            return rescue(function () use ($entityType) {
                return app(ModelAttributeDiscoveryService::class)
                    ->getAttributeOptions($entityType);
            }, []);
        }

        $currentFieldCode = $this->forSection ? null : $get('../../../../code');

        return rescue(function () use ($entityType, $currentFieldCode) {
            return CustomFields::customFieldModel()::query()
                ->forMorphEntity($entityType)
                ->when($currentFieldCode, fn (mixed $query) => $query->where('code', '!=', $currentFieldCode))
                ->orderBy('name')
                ->pluck('name', 'code')
                ->toArray();
        }, []);
    }

    /**
     * @return array<string, string>
     */
    private function getCompatibleOperators(Get $get): array
    {
        if ($this->isModelAttributeSource($get)) {
            $dataType = $this->getModelAttributeDataType($get);

            return $dataType
                ? $dataType->getCompatibleOperatorOptions()
                : VisibilityOperator::options();
        }

        $fieldData = $this->getFieldTypeData($get);

        return $fieldData
            ? $fieldData->getCompatibleOperatorOptions()
            : VisibilityOperator::options();
    }

    private function getModelAttributeDataType(Get $get): ?FieldDataType
    {
        $fieldCode = $get('field_code');
        if (blank($fieldCode)) {
            return null;
        }

        $entityType = $this->getEntityType($get);
        if (blank($entityType)) {
            return null;
        }

        return rescue(function () use ($entityType, $fieldCode) {
            return app(ModelAttributeDiscoveryService::class)
                ->getAttributeDataType($entityType, $fieldCode);
        });
    }

    private function getFieldTypeData(Get $get): ?object
    {
        $fieldCode = $get('field_code');
        if (blank($fieldCode)) {
            return null;
        }

        $field = $this->getCustomField($fieldCode, $get);
        if (! $field instanceof CustomField) {
            return null;
        }

        return rescue(
            fn () => CustomFieldsType::getFieldType($field->type)
        );
    }

    private function getCustomField(string $fieldCode, Get $get): ?CustomField
    {
        $entityType = $this->getEntityType($get);
        if (blank($entityType)) {
            return null;
        }

        return rescue(function () use ($entityType, $fieldCode) {
            return CustomFields::customFieldModel()::query()
                ->forMorphEntity($entityType)
                ->where('code', $fieldCode)
                ->first();
        });
    }

    private function getEntityType(Get $get): ?string
    {
        if ($this->forSection) {
            return $this->sectionEntityType;
        }

        return $get('../../../../entity_type')
            ?? request('entityType')
            ?? request()->route('entityType');
    }

    private function resetConditionValues(Get $get, Set $set): void
    {
        $this->clearAllValueFields($set);
        $set('operator', array_key_first($this->getCompatibleOperators($get)));
    }

    private function clearAllValueFields(Set $set): void
    {
        $set('value', null);
        $set('text_value', null);
        $set('boolean_value', false);
        $set('single_value', null);
        $set('multiple_values', []);
    }

    private function isContainsOperator(?string $operator): bool
    {
        return in_array($operator, [
            VisibilityOperator::CONTAINS->value,
            VisibilityOperator::NOT_CONTAINS->value,
        ], true);
    }
}
