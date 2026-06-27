<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Schemas;

use Closure;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Relaticle\CustomFields\Contracts\ValidationCapability;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\DescriptionPosition;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Forms\Components\TypeField;
use Relaticle\CustomFields\Filament\Management\Forms\Components\VisibilityComponent;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\TenantContextService;

class FieldForm implements FormInterface
{
    /**
     * Disable field when editing a system-defined custom field.
     */
    private static function disabledForSystemFields(): Closure
    {
        return fn (?CustomField $record): bool => $record?->isSystemDefined() ?? false;
    }

    /**
     * Get type-specific settings schema components.
     *
     * @return array<int, Component>
     */
    private static function getTypeSettingsSchema(): array
    {
        $components = [];

        foreach (CustomFieldsType::toCollection() as $fieldTypeData) {
            if ($fieldTypeData->settingsSchema === null) {
                continue;
            }

            $schema = is_callable($fieldTypeData->settingsSchema)
                ? ($fieldTypeData->settingsSchema)()
                : $fieldTypeData->settingsSchema;

            foreach ($schema as $component) {
                $components[] = $component->visible(
                    fn (Get $get): bool => $get('type') === $fieldTypeData->key
                );
            }
        }

        return $components;
    }

    /**
     * Get validation schema components from registered capabilities.
     *
     * @return array<int, Component>
     */
    private static function getValidationSchema(): array
    {
        $components = [];

        foreach (CustomFieldsType::toCollection() as $fieldTypeData) {
            if ($fieldTypeData->validationCapabilities === []) {
                continue;
            }

            foreach ($fieldTypeData->validationCapabilities as $capabilityClass) {
                /** @var ValidationCapability $capability */
                $capability = app($capabilityClass);
                $capabilityComponents = $capability->formSchema('validation_rules');

                foreach ($capabilityComponents as $component) {
                    $components[] = $component->visible(
                        fn (Get $get): bool => $get('type') === $fieldTypeData->key
                    );
                }
            }
        }

        return $components;
    }

    /**
     * @return array<int, Component>
     */
    public static function schema(bool $withOptionsRelationship = true): array
    {
        $optionsRepeater = Repeater::make('options')
            ->table([
                TableColumn::make('Color')->width('150px')->hiddenHeaderLabel(),
                TableColumn::make('Name')->hiddenHeaderLabel(),
            ])
            ->schema([
                ColorPicker::make('settings.color')
                    ->columnSpan(3)
                    ->hexColor()
                    ->visible(
                        fn (
                            Get $get
                        ): bool => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS) &&
                            $get('../../settings.enable_option_colors')
                    ),
                TextInput::make('name')
                    ->required()
                    ->columnSpan(9)
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                            if (blank($value)) {
                                return;
                            }

                            $hasDuplicate = collect($get('../../options') ?? [])
                                ->pluck('name')
                                ->filter()
                                ->map(fn ($name) => mb_strtolower($name))
                                ->duplicates()
                                ->contains(mb_strtolower($value));

                            if ($hasDuplicate) {
                                $fail(__('validation.distinct'));
                            }
                        },
                    ]),
            ])
            ->columns(12)
            ->columnSpanFull()
            ->requiredUnless('type', function (callable $get) {
                $fieldType = $get('type');
                if (! $fieldType) {
                    return false;
                }

                return CustomFieldsType::toCollection()->acceptsArbitraryValues()->pluck('key')->toArray();
            })
            ->hiddenLabel()
            ->defaultItems(1)
            ->addActionLabel(
                __('custom-fields::custom-fields.field.form.options.add')
            )
            ->columnSpanFull()
            ->label(__('custom-fields::custom-fields.field.form.options.label'))
            ->visible(
                fn (Get $get): bool => $get('type') !== null
                    && CustomFieldsType::getFieldType($get('type'))->dataType->isChoiceField()
                    && ! CustomFieldsType::getFieldType($get('type'))->withoutUserOptions
                    && ! CustomFieldsType::getFieldType($get('type'))->requiresLookupType
            )
            ->mutateRelationshipDataBeforeCreateUsing(function (
                array $data
            ): array {
                if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                    $data[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
                }

                return $data;
            });

        if ($withOptionsRelationship) {
            $optionsRepeater = $optionsRepeater->relationship();
        }

        $optionsRepeater->reorderable()->orderColumn('sort_order');

        // Build general schema
        $generalSchema = [
            Hidden::make('entity_type')
                ->default(
                    fn () => request(
                        'entityType',
                        (Entities::withCustomFields()->first()?->getAlias()) ?? ''
                    )
                ),
            Grid::make()
                ->columns(fn (): int => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE) ? 2 : 3)
                ->columnSpanFull()
                ->schema([
                    TypeField::make('type')
                        ->label(__('custom-fields::custom-fields.field.form.type'))
                        ->disabled(fn (?CustomField $record): bool => (bool) $record?->exists)
                        ->live()
                        ->afterStateHydrated(function (
                            Select $component,
                            mixed $state,
                            ?CustomField $record
                        ): void {
                            if (blank($state)) {
                                $component->state(
                                    $record->type ?? CustomFieldsType::toCollection()->first()->key
                                );
                            }
                        })
                        ->required(),
                    TextInput::make('name')
                        ->label(__('custom-fields::custom-fields.field.form.name'))
                        ->live(onBlur: true)
                        ->required()
                        ->maxLength(50)
                        ->disabled(self::disabledForSystemFields())
                        ->unique(
                            table: CustomFields::customFieldModel(),
                            column: 'name',
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                                ->where('entity_type', $get('entity_type'))
                                ->when(
                                    FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY),
                                    fn (Unique $rule) => $rule->where(
                                        config('custom-fields.database.column_names.tenant_foreign_key'),
                                        TenantContextService::getCurrentTenantId()
                                    )
                                )
                        )
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                            $old ??= '';
                            $state ??= '';

                            if (($get('code') ?? '') !== Str::of($old)->slug('_')->toString()) {
                                return;
                            }

                            $set('code', Str::of($state)->slug('_')->toString());
                        }),
                    TextInput::make('code')
                        ->label(__('custom-fields::custom-fields.field.form.code'))
                        ->live(onBlur: true)
                        ->required(fn (): bool => ! FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE))
                        ->alphaDash()
                        ->maxLength(50)
                        ->disabled(self::disabledForSystemFields())
                        ->visible(fn (): bool => ! FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE))
                        ->unique(
                            table: CustomFields::customFieldModel(),
                            column: 'code',
                            ignoreRecord: true,
                            modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                                ->where('entity_type', $get('entity_type'))
                                ->when(
                                    FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY),
                                    fn (Unique $rule) => $rule->where(
                                        config('custom-fields.database.column_names.tenant_foreign_key'),
                                        TenantContextService::getCurrentTenantId()
                                    )
                                )
                        )
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('code', Str::of($state)->slug('_')->toString());
                        }),
                ]),
            Textarea::make('settings.description')
                ->label(__('custom-fields::custom-fields.field.form.description'))
                ->maxLength(config('custom-fields.fields.description_max_length', 255))
                ->rows(2)
                ->live(onBlur: true)
                ->columnSpanFull()
                ->visible(fn (): bool => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_DESCRIPTION)),
            Select::make('settings.description_position')
                ->label(__('custom-fields::custom-fields.field.form.description_position'))
                ->options(DescriptionPosition::class)
                ->placeholder(__('custom-fields::custom-fields.field.form.description_position_options.below'))
                ->visible(fn (Get $get): bool => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_DESCRIPTION) &&
                    FeatureManager::isEnabled(CustomFieldsFeature::FIELD_DESCRIPTION_POSITION) &&
                    filled($get('settings.description'))
                ),
            Fieldset::make(
                __(
                    'custom-fields::custom-fields.field.form.settings'
                )
            )
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    // Visibility settings
                    Toggle::make('settings.visible_in_list')
                        ->inline()
                        ->live()
                        ->label(
                            __(
                                'custom-fields::custom-fields.field.form.visible_in_list'
                            )
                        )
                        ->afterStateHydrated(function (
                            Toggle $component,
                            ?Model $record
                        ): void {
                            if (is_null($record)) {
                                $component->state(true);
                            }
                        }),
                    Toggle::make('settings.visible_in_view')
                        ->inline()
                        ->label(
                            __(
                                'custom-fields::custom-fields.field.form.visible_in_view'
                            )
                        )
                        ->afterStateHydrated(function (
                            Toggle $component,
                            ?Model $record
                        ): void {
                            if (is_null($record)) {
                                $component->state(true);
                            }
                        }),
                    Toggle::make('settings.list_toggleable_hidden')
                        ->inline()
                        ->label(
                            __(
                                'custom-fields::custom-fields.field.form.list_toggleable_hidden'
                            )
                        )
                        ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.list_toggleable_hidden_hint'))
                        ->visible(
                            fn (Get $get): bool => $get(
                                'settings.visible_in_list'
                            ) &&
                                FeatureManager::isEnabled(CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS)
                        )
                        ->afterStateHydrated(function (
                            Toggle $component,
                            ?Model $record
                        ): void {
                            if (is_null($record)) {
                                $component->state(
                                    FeatureManager::isEnabled(CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS_HIDDEN_DEFAULT)
                                );
                            }
                        }),
                    // Data settings
                    Toggle::make('settings.searchable')
                        ->inline()
                        ->visible(
                            fn (
                                Get $get
                            ): bool => CustomFieldsType::getFieldType($get('type'))->searchable ?? false
                        )
                        ->disabled(
                            fn (Get $get): bool => $get(
                                'settings.encrypted'
                            ) === true
                        )
                        ->label(
                            __(
                                'custom-fields::custom-fields.field.form.searchable'
                            )
                        )
                        ->afterStateHydrated(function (
                            Toggle $component,
                            mixed $state
                        ): void {
                            if (is_null($state)) {
                                $component->state(false);
                            }
                        }),
                    Toggle::make('settings.encrypted')
                        ->inline()
                        ->live()
                        ->disabled(
                            fn (
                                ?CustomField $record
                            ): bool => (bool) $record?->exists
                        )
                        ->dehydrated()
                        ->label(
                            __(
                                'custom-fields::custom-fields.field.form.encrypted'
                            )
                        )
                        ->visible(
                            fn (
                                Get $get
                            ): bool => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_ENCRYPTION) &&
                                CustomFieldsType::getFieldType($get('type'))->encryptable
                        )
                        ->default(false),
                    // Appearance settings
                    Toggle::make('settings.enable_option_colors')
                        ->inline()
                        ->live()
                        ->label(
                            __(
                                'custom-fields::custom-fields.field.form.enable_option_colors'
                            )
                        )
                        ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.enable_option_colors_help'))
                        ->visible(
                            fn (
                                Get $get
                            ): bool => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS) &&
                                in_array((string) $get('type'), [
                                    'select',
                                    'multi_select',
                                    'tags-input',
                                ], true)
                        ),
                    // Multi-value settings
                    Toggle::make('settings.allow_multiple')
                        ->inline()
                        ->live()
                        ->label(__('custom-fields::custom-fields.field.form.allow_multiple'))
                        ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.allow_multiple_help'))
                        ->visible(
                            fn (Get $get): bool => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_MULTI_VALUE) &&
                                CustomFieldsType::getFieldType($get('type'))?->supportsMultiValue === true
                        )
                        ->afterStateUpdated(function (Set $set, bool $state): void {
                            if ($state) {
                                $set('settings.max_values', 2);
                            }
                        })
                        ->default(false),
                    TextInput::make('settings.max_values')
                        ->label(
                            __(
                                'custom-fields::custom-fields.field.form.max_values'
                            )
                        )
                        ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.max_values_help'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(5)
                        ->visible(function (Get $get): bool {
                            $fieldType = CustomFieldsType::getFieldType($get('type'));

                            return FeatureManager::isEnabled(CustomFieldsFeature::FIELD_MULTI_VALUE) &&
                                $fieldType?->supportsMultiValue === true &&
                                $fieldType->requiresLookupType !== true &&
                                $get('settings.allow_multiple') === true;
                        }),
                    // Uniqueness constraint
                    Toggle::make('settings.unique_per_entity_type')
                        ->inline()
                        ->label(
                            __(
                                'custom-fields::custom-fields.field.form.unique_per_entity_type'
                            )
                        )
                        ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.unique_per_entity_type_help'))
                        ->visible(
                            fn (Get $get): bool => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_UNIQUE_VALUE) &&
                                CustomFieldsType::getFieldType($get('type'))?->supportsUniqueConstraint === true
                        )
                        ->disabled(self::disabledForSystemFields())
                        ->default(false),
                ]),

            // Dynamic type-specific settings from field type definition
            ...self::getTypeSettingsSchema(),

        ];

        $generalSchema[] = Select::make('lookup_type')
            ->label(__('custom-fields::custom-fields.field.form.lookup_type.label'))
            ->visible(
                fn (Get $get): bool => $get('type') !== null
                    && CustomFieldsType::getFieldType($get('type'))?->requiresLookupType === true
            )
            ->disabled(fn (?CustomField $record): bool => (bool) $record?->exists)
            ->options(Entities::getLookupOptions())
            ->default((Entities::asLookupSources()->first()?->getAlias()) ?? '')
            ->required();

        $generalSchema[] = $optionsRepeater;

        // Build additional tabs based on feature flags
        $additionalTabs = [];

        if (FeatureManager::isEnabled(CustomFieldsFeature::FIELD_VALIDATION_RULES)) {
            $additionalTabs[] = Tab::make(
                __('custom-fields::custom-fields.field.form.validation.label')
            )->schema([
                Toggle::make('validation_rules.required')
                    ->inline()
                    ->label(__('custom-fields::custom-fields.field.form.validation.required'))
                    ->default(false)
                    ->columnSpanFull(),
                ...self::getValidationSchema(),
            ])->columns(2);
        }

        if (FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)) {
            $additionalTabs[] = Tab::make(
                __('custom-fields::custom-fields.field.form.visibility_settings')
            )->schema([VisibilityComponent::make()]);
        }

        // If no additional tabs, return schema directly without tabs wrapper
        if ($additionalTabs === []) {
            return $generalSchema;
        }

        // Otherwise, wrap in tabs component
        return [
            Tabs::make()
                ->tabs([
                    Tab::make(
                        __('custom-fields::custom-fields.field.form.general')
                    )->schema($generalSchema),
                    ...$additionalTabs,
                ])
                ->columns(2)
                ->columnSpanFull()
                ->contained(false),
        ];
    }
}
