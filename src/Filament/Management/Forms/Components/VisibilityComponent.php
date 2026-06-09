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
use Illuminate\Database\Eloquent\Model;
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
use Relaticle\CustomFields\Services\RelationConditionResolver;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Support\RelationConditionConfig;

final class VisibilityComponent extends Component
{
    protected string $view = 'filament-schemas::components.grid';

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
        $schema = [];

        $modelAttrsEnabled = FeatureManager::isEnabled(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS);
        $relationsAvailable = app(RelationConditionConfig::class)
            ->isRelationSourceAvailable((string) $this->getEntityType());

        $showSourceSelect = $modelAttrsEnabled || $relationsAvailable;

        if ($showSourceSelect) {
            $schema[] = Select::make('source')
                ->label(__('custom-fields::custom-fields.visibility.source'))
                ->options(fn (Get $get): array => $this->getAvailableSourceOptions($get))
                ->default(ConditionSource::CustomField)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $this->resetConditionValues(null, $set))
                ->columnSpan(3);
        } else {
            $schema[] = Hidden::make('source')->default(ConditionSource::CustomField->value);
        }

        $fieldCodeSpan = $showSourceSelect ? 3 : 4;
        $operatorSpan = $showSourceSelect ? 2 : 3;
        $valueSpan = $showSourceSelect ? 4 : 5;

        $schema[] = Select::make('field_code')
            ->label(__('custom-fields::custom-fields.visibility.field'))
            ->options(fn (Get $get): array => $this->getAvailableFields($get))
            ->required()
            ->live()
            ->afterStateUpdated(fn (Get $get, Set $set) => $this->resetValuesAndOperator($get, $set))
            ->columnSpan($fieldCodeSpan);

        $schema[] = Select::make('operator')
            ->label(__('custom-fields::custom-fields.visibility.operator'))
            ->options(fn (Get $get): array => $this->getCompatibleOperators($get))
            ->required()
            ->live()
            ->afterStateUpdated(fn (Set $set) => $this->clearAllValueFields($set))
            ->columnSpan($operatorSpan);

        $schema = [...$schema, ...$this->getValueInputComponents($valueSpan)];

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
                ->options(fn (Get $get): array => $this->getFieldOptions($get))
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
                ->options(fn (Get $get): array => $this->getFieldOptions($get))
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
                ->options(fn (Get $get): array => $this->getRelationValueOptions($get))
                ->visible(fn (Get $get): bool => $this->isRelationAttributeSource($get) && $this->operatorRequiresValue($get))
                ->afterStateHydrated(fn (Select $component, Get $get): Select => $component->state(value($get('value')) ? (array) $get('value') : []))
                ->afterStateUpdated(fn (mixed $state, Set $set): mixed => $set('value', $state))
                ->columnSpan($columnSpan),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function getAvailableSourceOptions(Get $get): array
    {
        $entityType = $this->getEntityType($get);

        $options = [
            ConditionSource::CustomField->value => ConditionSource::CustomField->getLabel(),
        ];

        if (FeatureManager::isEnabled(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS)) {
            $options[ConditionSource::ModelAttribute->value] = ConditionSource::ModelAttribute->getLabel();
        }

        if (! blank($entityType) && app(RelationConditionConfig::class)->isRelationSourceAvailable($entityType)) {
            $options[ConditionSource::RelationAttribute->value] = ConditionSource::RelationAttribute->getLabel();
        }

        return $options;
    }

    private function sourceIs(Get $get, ConditionSource $expected): bool
    {
        $source = $get('source');

        if ($source instanceof ConditionSource) {
            return $source === $expected;
        }

        return $source === $expected->value;
    }

    private function isModelAttributeSource(Get $get): bool
    {
        return $this->sourceIs($get, ConditionSource::ModelAttribute);
    }

    private function isRelationAttributeSource(Get $get): bool
    {
        return $this->sourceIs($get, ConditionSource::RelationAttribute);
    }

    private function shouldShowSingleSelect(Get $get): bool
    {
        if (! $this->operatorRequiresValue($get)) {
            return false;
        }

        if ($this->isModelAttributeSource($get)) {
            return false;
        }

        if ($this->isRelationAttributeSource($get)) {
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

        if ($this->isModelAttributeSource($get)) {
            return false;
        }

        if ($this->isRelationAttributeSource($get)) {
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

        if ($this->isRelationAttributeSource($get)) {
            return false;
        }

        if ($this->isModelAttributeSource($get)) {
            $entityType = $this->getEntityType($get);
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

        $fieldData = $this->getFieldTypeData($get);

        return $fieldData && $fieldData->dataType === FieldDataType::BOOLEAN;
    }

    private function shouldShowTextInput(Get $get): bool
    {
        if (! $this->operatorRequiresValue($get)) {
            return false;
        }

        if ($this->isRelationAttributeSource($get)) {
            return false;
        }

        if ($this->isModelAttributeSource($get)) {
            return ! $this->shouldShowToggle($get);
        }

        $fieldData = $this->getFieldTypeData($get);
        if ($fieldData === null) {
            return true;
        }

        return ! $fieldData->dataType->isChoiceField() &&
               $fieldData->dataType !== FieldDataType::BOOLEAN;
    }

    /**
     * @return array<string, string>
     */
    private function getFieldOptions(Get $get): array
    {
        if ($this->isModelAttributeSource($get)) {
            return [];
        }

        $fieldCode = $get('field_code');
        if (blank($fieldCode)) {
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
            return 'Enter comparison value';
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
        $mode = $get('settings.visibility.mode');

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

        if ($this->isRelationAttributeSource($get)) {
            return app(RelationConditionConfig::class)->relationsFor($entityType);
        }

        if ($this->isModelAttributeSource($get)) {
            return rescue(
                fn (): array => app(ModelAttributeDiscoveryService::class)->getAttributeOptions($entityType),
                []
            );
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
        if ($this->isRelationAttributeSource($get)) {
            return [
                VisibilityOperator::IS_IN->value => VisibilityOperator::IS_IN->getLabel(),
                VisibilityOperator::IS_NOT_IN->value => VisibilityOperator::IS_NOT_IN->getLabel(),
            ];
        }

        if ($this->isModelAttributeSource($get)) {
            return collect(VisibilityOperator::options())
                ->except([VisibilityOperator::IS_IN->value, VisibilityOperator::IS_NOT_IN->value])
                ->all();
        }

        $fieldData = $this->getFieldTypeData($get);

        return $fieldData
            ? $fieldData->getCompatibleOperatorOptions()
            : collect(VisibilityOperator::options())
                ->except([VisibilityOperator::IS_IN->value, VisibilityOperator::IS_NOT_IN->value])
                ->all();
    }

    /**
     * @return array<int|string, string>
     */
    private function getRelationValueOptions(Get $get): array
    {
        $path = $get('field_code');

        if (blank($path)) {
            return [];
        }

        $entityType = $this->getEntityType($get);
        if (blank($entityType)) {
            return [];
        }

        $related = app(RelationConditionResolver::class)->resolveTerminalRelatedModel($entityType, (string) $path);

        if (! $related instanceof Model) {
            return [];
        }

        static $labelColumns = [];
        $modelClass = $related::class;
        if (! isset($labelColumns[$modelClass])) {
            $labelColumns[$modelClass] = collect(['name', 'title', 'label'])
                ->first(fn (string $column): bool => $related->getConnection()->getSchemaBuilder()->hasColumn($related->getTable(), $column))
                ?? $related->getKeyName();
        }

        $labelColumn = $labelColumns[$modelClass];

        return $related::query()->pluck($labelColumn, $related->getKeyName())->all();
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

    private function getEntityType(?Get $get = null): ?string
    {
        if ($this->forSection && $this->sectionEntityType) {
            return $this->sectionEntityType;
        }

        return ($get instanceof Get ? $get('../../../../entity_type') : null)
            ?? request('entityType')
            ?? request()->route('entityType');
    }

    private function resetConditionValues(?Get $get, Set $set): void
    {
        $this->clearAllValueFields($set);
        $set('field_code', null);

        if ($get instanceof Get) {
            $set('operator', array_key_first($this->getCompatibleOperators($get)));
        }
    }

    private function resetValuesAndOperator(Get $get, Set $set): void
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
