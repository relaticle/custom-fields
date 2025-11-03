<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Relaticle\CustomFields\Data\FieldTypeData;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\Facades\CustomFieldsType;

final class CustomFieldValidationComponent extends Component
{
    protected string $view = 'filament-schemas::components.grid';

    public function __construct(
    ) {
        $this->schema([$this->buildValidationRulesRepeater()]);
        $this->columnSpanFull();
    }

    public static function make(): self
    {
        return app(self::class);
    }

    private function buildValidationRulesRepeater(): Repeater
    {
        return Repeater::make('validation_rules')
            ->label(
                __('custom-fields::custom-fields.field.form.validation.rules')
            )
            ->schema([
                Grid::make(3)->schema([
                    $this->buildRuleSelector(),
                    $this->buildRuleDescription(),
                    $this->buildRuleParametersRepeater(),
                ]),
            ])
            ->itemLabel(
                fn (array $state): string => $this->generateRuleLabel($state)
            )
            ->collapsible()
            ->collapsed(
                fn (Get $get): bool => count($get('validation_rules') ?? []) > 3
            )
            ->reorderable()
            ->reorderableWithButtons()
            ->deletable()
            ->cloneable()
            ->hintColor('danger')
            ->addable(fn (Get $get): bool => ! empty($get('type')))
            ->hiddenLabel()
            ->defaultItems(0)
            ->addActionLabel(
                __(
                    'custom-fields::custom-fields.field.form.validation.add_rule'
                )
            )
            ->columnSpanFull();
    }

    private function buildRuleSelector(): Select
    {
        return Select::make('name')
            ->label(
                __('custom-fields::custom-fields.field.form.validation.rule')
            )
            ->placeholder(
                __(
                    'custom-fields::custom-fields.field.form.validation.select_rule_placeholder'
                )
            )
            ->options(
                fn (Get $get): array => $this->getAvailableRuleOptions($get)
            )
            ->searchable()
            ->required()
            ->live()
            ->in(fn (Get $get): array => $this->getAllowedRuleValues($get))
            ->afterStateUpdated(
                fn (
                    Get $get,
                    Set $set,
                    ?string $state,
                    ?string $old
                ) => $this->handleRuleChange($set, $state, $old)
            )
            ->columnSpan(1);
    }

    private function buildRuleDescription(): TextEntry
    {
        return TextEntry::make('description')
            ->label(
                __(
                    'custom-fields::custom-fields.field.form.validation.description'
                )
            )
            ->state(
                fn (
                    Get $get
                ): string => ValidationRule::getDescriptionForRule(
                    $get('name')
                )
            )
            ->columnSpan(2);
    }

    private function buildRuleParametersRepeater(): Repeater
    {
        return Repeater::make('parameters')
            ->label(
                __(
                    'custom-fields::custom-fields.field.form.validation.parameters'
                )
            )
            ->simple(
                TextInput::make('value')
                    ->label(
                        __(
                            'custom-fields::custom-fields.field.form.validation.parameters_value'
                        )
                    )
                    ->required()
                    ->hiddenLabel()
                    ->rules(
                        fn (
                            Get $get,
                            Component $component
                        ): array => $this->getParameterValidationRules($get, $component)
                    )
                    ->hint(
                        fn (
                            Get $get,
                            Component $component
                        ): string => $this->getParameterHint($get, $component)
                    )
                    ->afterStateHydrated(
                        fn (
                            Get $get,
                            Set $set,
                            mixed $state,
                            Component $component
                        ) => $this->hydrateParameterValue(
                            $get,
                            $set,
                            $state,
                            $component
                        )
                    )
                    ->dehydrateStateUsing(
                        fn (
                            Get $get,
                            mixed $state,
                            Component $component
                        ): ?string => $this->dehydrateParameterValue(
                            $get,
                            $state,
                            $component
                        )
                    )
            )
            ->columnSpanFull()
            ->visible(
                fn (
                    Get $get
                ): bool => ValidationRule::hasParameterForRule(
                    $get('name')
                )
            )
            ->minItems(fn (Get $get): int => $this->getParameterCount($get))
            ->maxItems(fn (Get $get): int => $this->getMaxParameterCount($get))
            ->reorderable(false)
            ->deletable(fn (Get $get): bool => $this->canDeleteParameter($get))
            ->defaultItems(fn (Get $get): int => $this->getParameterCount($get))
            ->hint(fn (Get $get): string => $this->getParameterHint($get))
            ->hintColor('danger')
            ->addActionLabel(
                __(
                    'custom-fields::custom-fields.field.form.validation.add_parameter'
                )
            );
    }

    /**
     * @return array<string, string>
     */
    private function getAvailableRuleOptions(Get $get): array
    {
        $fieldTypeKey = $get('../../type');
        if (! $fieldTypeKey) {
            return [];
        }

        $allowedRules = $this->getAllowedValidationRulesForFieldType($fieldTypeKey);
        $existingRules = $get('../../validation_rules') ?? [];
        $currentRuleName = $get('name');

        return collect($allowedRules)
            ->reject(
                fn (
                    ValidationRule $rule
                ): bool => $this->isRuleDuplicate(
                    $existingRules,
                    $rule->value
                ) && $rule->value !== $currentRuleName
            )
            ->mapWithKeys(
                fn (ValidationRule $rule): array => [
                    $rule->value => $rule->getLabel(),
                ]
            )
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function getAllowedRuleValues(Get $get): array
    {
        $fieldType = $this->getFieldType($get);

        if (! $fieldType instanceof FieldTypeData) {
            return [];
        }

        return collect($fieldType->validationRules)
            ->pluck('value')
            ->toArray();
    }

    private function handleRuleChange(
        Set $set,
        ?string $state,
        ?string $old
    ): void {
        if ($old === $state) {
            return;
        }

        $set('parameters', []);

        if (in_array($state, [null, '', '0'], true)) {
            return;
        }

        $rule = ValidationRule::tryFrom($state);
        if ($rule && $rule->allowedParameterCount() > 0) {
            $parameters = array_fill(0, $rule->allowedParameterCount(), [
                'value' => '',
                'key' => Str::uuid()->toString(),
            ]);
            $set('parameters', $parameters);
        }
    }

    /**
     * @return array<int, string>
     */
    private function getParameterValidationRules(
        Get $get,
        Component $component
    ): array {
        $ruleName = $get('../../name');
        $parameterIndex = $this->getParameterIndex($component);

        return ValidationRule::getParameterValidationRuleFor(
            $ruleName,
            $parameterIndex
        );
    }

    private function getParameterHint(
        Get $get,
        ?Component $component = null
    ): string {
        $ruleName = $get('name') ?? $get('../../name');

        if (empty($ruleName)) {
            return '';
        }

        if ($component instanceof Component) {
            $parameterIndex = $this->getParameterIndex($component);

            return ValidationRule::getParameterHelpTextFor(
                $ruleName,
                $parameterIndex
            );
        }

        // For repeater-level hints when parameters are insufficient
        $rule = ValidationRule::tryFrom($ruleName);
        $parameters = $get('parameters') ?? [];

        if (
            ! $rule ||
            $rule->allowedParameterCount() <= 0 ||
            count($parameters) >= $rule->allowedParameterCount()
        ) {
            return '';
        }

        $requiredCount = $rule->allowedParameterCount();

        return match ($requiredCount) {
            2 => match ($rule) {
                ValidationRule::BETWEEN => __(
                    'custom-fields::custom-fields.validation.between_validation_error'
                ),
                ValidationRule::DIGITS_BETWEEN => __(
                    'custom-fields::custom-fields.validation.digits_between_validation_error'
                ),
                ValidationRule::DECIMAL => __(
                    'custom-fields::custom-fields.validation.decimal_validation_error'
                ),
                default => __(
                    'custom-fields::custom-fields.validation.parameter_missing',
                    ['count' => $requiredCount]
                ),
            },
            default => __(
                'custom-fields::custom-fields.validation.parameter_missing',
                ['count' => $requiredCount]
            ),
        };
    }

    private function hydrateParameterValue(
        Get $get,
        Set $set,
        mixed $state,
        Component $component
    ): void {
        if ($state === null) {
            return;
        }

        $ruleName = $get('../../name');
        if (empty($ruleName)) {
            return;
        }

        $parameterIndex = $this->getParameterIndex($component);
        $normalizedValue = ValidationRule::normalizeParameterValue(
            $ruleName,
            (string) $state,
            $parameterIndex
        );

        $set('value', $normalizedValue);
    }

    private function dehydrateParameterValue(
        Get $get,
        mixed $state,
        Component $component
    ): ?string {
        if ($state === null) {
            return null;
        }

        $ruleName = $get('../../name');
        if (empty($ruleName)) {
            return $state;
        }

        $parameterIndex = $this->getParameterIndex($component);

        return ValidationRule::normalizeParameterValue(
            $ruleName,
            (string) $state,
            $parameterIndex
        );
    }

    private function getParameterCount(Get $get): int
    {
        $ruleName = $get('name');
        if (empty($ruleName)) {
            return 1;
        }

        $rule = ValidationRule::tryFrom($ruleName);

        return $rule && $rule->allowedParameterCount() > 0
            ? $rule->allowedParameterCount()
            : 1;
    }

    private function getMaxParameterCount(Get $get): int
    {
        return ValidationRule::getAllowedParametersCountForRule(
            $get('name')
        );
    }

    private function canDeleteParameter(Get $get): bool
    {
        $ruleName = $get('name');
        if (empty($ruleName)) {
            return true;
        }

        $rule = ValidationRule::tryFrom($ruleName);
        $parameterCount = count($get('parameters') ?? []);

        return ! (
            $rule &&
            $rule->allowedParameterCount() > 0 &&
            $parameterCount <= $rule->allowedParameterCount()
        );
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function generateRuleLabel(array $state): string
    {
        $ruleName = $state['name'] ?? '';
        $parameters = $state['parameters'] ?? [];

        return ValidationRule::getLabelForRule(
            $ruleName,
            $parameters
        );
    }

    /**
     * Get allowed validation rules for a field type (built-in or custom).
     *
     * @return array<int, ValidationRule>
     */
    private function getAllowedValidationRulesForFieldType(
        string $fieldTypeKey
    ): array {
        $fieldType = CustomFieldsType::getFieldType($fieldTypeKey);

        return $fieldType->validationRules;
    }

    private function getFieldType(Get $get): ?FieldTypeData
    {
        $type = $get('../../type');

        if ($type === null) {
            return null;
        }

        return CustomFieldsType::getFieldType($type);
    }

    /**
     * @param  array<string, mixed>  $existingRules
     */
    private function isRuleDuplicate(array $existingRules, string $rule): bool
    {
        return collect($existingRules)->contains(
            fn (array $existingRule): bool => ($existingRule['name'] ?? '') === $rule
        );
    }

    private function getParameterIndex(Component $component): int
    {
        $statePath = $component->getStatePath();

        if (
            in_array(
                preg_match(
                    "/parameters\.([^.]+)/",
                    (string) $statePath,
                    $matches
                ),
                [0, false],
                true
            )
        ) {
            return 0;
        }

        $key = $matches[1];

        // Try to get index from container state
        $container = $component->getContainer();
        $repeater = $container->getParentComponent();
        $parameters = $repeater->getState();

        if (is_array($parameters)) {
            $keys = array_keys($parameters);
            $index = array_search($key, $keys, true);

            if ($index !== false) {
                return (int) $index;
            }

            if (is_numeric($key)) {
                return (int) $key;
            }
        }

        // Fallback: extract from component ID
        $idParts = explode('-', (string) $component->getId());
        if (count($idParts) > 1) {
            $lastPart = end($idParts);
            if (is_numeric($lastPart)) {
                return (int) $lastPart;
            }
        }

        return 0;
    }
}
