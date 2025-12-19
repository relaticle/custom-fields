<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Schemas;

use Exception;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Forms\Components\CustomFieldValidationComponent;
use Relaticle\CustomFields\Filament\Management\Forms\Components\TypeField;
use Relaticle\CustomFields\Filament\Management\Forms\Components\VisibilityComponent;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\TenantContextService;

class FieldForm implements FormInterface
{
    /**
     * @return array<int, Component>
     *
     * @throws Exception
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
                TextInput::make('name')->required()->columnSpan(9)->distinct(),
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
                fn (Get $get): bool => $get('options_lookup_type') === 'options'
                    && $get('type') !== null
                    && CustomFieldsType::getFieldType($get('type'))->dataType->isChoiceField()
                    && ! CustomFieldsType::getFieldType($get('type'))->withoutUserOptions
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

        return [
            Tabs::make()
                ->tabs([
                    Tab::make(
                        __('custom-fields::custom-fields.field.form.general')
                    )->schema([
                        Select::make('entity_type')
                            ->label(
                                __(
                                    'custom-fields::custom-fields.field.form.entity_type'
                                )
                            )
                            ->options(Entities::getOptions(onlyCustomFields: true))
                            ->disabled()
                            ->default(
                                fn () => request(
                                    'entityType',
                                    (Entities::withCustomFields()->first()?->getAlias()) ?? ''
                                )
                            )
                            ->required(),
                        TypeField::make('type')
                            ->label(
                                __(
                                    'custom-fields::custom-fields.field.form.type'
                                )
                            )
                            ->disabled(
                                fn (
                                    ?CustomField $record
                                ): bool => (bool) $record?->exists
                            )
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
                            ->label(
                                __(
                                    'custom-fields::custom-fields.field.form.name'
                                )
                            )
                            ->helperText(
                                __(
                                    'custom-fields::custom-fields.field.form.name_helper_text'
                                )
                            )
                            ->live(onBlur: true)
                            ->required()
                            ->maxLength(50)
                            ->disabled(
                                fn (
                                    ?CustomField $record
                                ): bool => (bool) $record?->system_defined
                            )
                            ->unique(
                                table: CustomFields::customFieldModel(),
                                column: 'name',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (
                                    Unique $rule,
                                    Get $get
                                ) => $rule
                                    ->where('entity_type', $get('entity_type'))
                                    ->when(
                                        FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY),
                                        fn (Unique $rule) => $rule->where(
                                            config(
                                                'custom-fields.database.column_names.tenant_foreign_key'
                                            ),
                                            TenantContextService::getCurrentTenantId()
                                        )
                                    )
                            )
                            ->afterStateUpdated(function (
                                Get $get,
                                Set $set,
                                ?string $old,
                                ?string $state
                            ): void {
                                $old ??= '';
                                $state ??= '';

                                if (
                                    ($get('code') ?? '') !==
                                    Str::of($old)->slug('_')->toString()
                                ) {
                                    return;
                                }

                                $set(
                                    'code',
                                    Str::of($state)->slug('_')->toString()
                                );
                            }),
                        TextInput::make('code')
                            ->label(
                                __(
                                    'custom-fields::custom-fields.field.form.code'
                                )
                            )
                            ->helperText(
                                __(
                                    'custom-fields::custom-fields.field.form.code_helper_text'
                                )
                            )
                            ->live(onBlur: true)
                            ->required()
                            ->alphaDash()
                            ->maxLength(50)
                            ->disabled(
                                fn (
                                    ?CustomField $record
                                ): bool => (bool) $record?->system_defined
                            )
                            ->unique(
                                table: CustomFields::customFieldModel(),
                                column: 'code',
                                ignoreRecord: true,
                                modifyRuleUsing: fn (
                                    Unique $rule,
                                    Get $get
                                ) => $rule
                                    ->where('entity_type', $get('entity_type'))
                                    ->when(
                                        FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY),
                                        fn (Unique $rule) => $rule->where(
                                            config(
                                                'custom-fields.database.column_names.tenant_foreign_key'
                                            ),
                                            TenantContextService::getCurrentTenantId()
                                        )
                                    )
                            )
                            ->afterStateUpdated(function (
                                Set $set,
                                ?string $state
                            ): void {
                                $set(
                                    'code',
                                    Str::of($state)->slug('_')->toString()
                                );
                            }),
                        Fieldset::make(
                            __(
                                'custom-fields::custom-fields.field.form.settings'
                            )
                        )
                            ->columnSpanFull()
                            ->columns(2)
                            ->schema([
                                // Visibility settings
                                Toggle::make('settings.visible_in_list')
                                    ->inline(false)
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
                                    ->inline(false)
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
                                    ->inline(false)
                                    ->label(
                                        __(
                                            'custom-fields::custom-fields.field.form.list_toggleable_hidden'
                                        )
                                    )
                                    ->helperText(
                                        __(
                                            'custom-fields::custom-fields.field.form.list_toggleable_hidden_hint'
                                        )
                                    )
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
                                    ->inline(false)
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
                                    ->inline(false)
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
                                    ->inline(false)
                                    ->live()
                                    ->label(
                                        __(
                                            'custom-fields::custom-fields.field.form.enable_option_colors'
                                        )
                                    )
                                    ->helperText(
                                        __(
                                            'custom-fields::custom-fields.field.form.enable_option_colors_help'
                                        )
                                    )
                                    ->visible(
                                        fn (
                                            Get $get
                                        ): bool => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS) &&
                                            in_array((string) $get('type'), [
                                                'select',
                                                'multi_select',
                                            ])
                                    ),
                            ]),

                        Select::make('options_lookup_type')
                            ->label(
                                __(
                                    'custom-fields::custom-fields.field.form.options_lookup_type.label'
                                )
                            )
                            ->visible(
                                fn (Get $get): bool => $get('type') !== null
                                    && CustomFieldsType::getFieldType($get('type'))->dataType->isChoiceField()
                                    && ! CustomFieldsType::getFieldType($get('type'))->withoutUserOptions
                            )
                            ->disabled(
                                fn (
                                    ?CustomField $record
                                ): bool => (bool) $record?->system_defined
                            )
                            ->live()
                            ->options([
                                'options' => __(
                                    'custom-fields::custom-fields.field.form.options_lookup_type.options'
                                ),
                                'lookup' => __(
                                    'custom-fields::custom-fields.field.form.options_lookup_type.lookup'
                                ),
                            ])
                            ->afterStateHydrated(function (
                                Select $component,
                                mixed $state,
                                ?CustomField $record,
                                Get $get
                            ): void {
                                if (blank($state)) {
                                    $optionsLookupType = $record?->lookup_type
                                        ? 'lookup'
                                        : 'options';

                                    $component->state($optionsLookupType);
                                }
                            })
                            ->afterStateUpdated(function (
                                Select $component,
                                ?string $state,
                                Set $set,
                                ?CustomField $record
                            ): void {
                                if ($state === 'options') {
                                    $set('lookup_type', null, true, true);
                                } else {
                                    $set(
                                        'lookup_type',
                                        $record->lookup_type ??
                                        (Entities::asLookupSources()->first()?->getAlias()) ?? ''
                                    );
                                }
                            })
                            ->dehydrated(false)
                            ->required(),
                        Select::make('lookup_type')
                            ->label(
                                __(
                                    'custom-fields::custom-fields.field.form.lookup_type.label'
                                )
                            )
                            ->visible(
                                fn (Get $get): bool => $get(
                                    'options_lookup_type'
                                ) === 'lookup'
                            )
                            ->live()
                            ->options(Entities::getLookupOptions())
                            ->default((Entities::asLookupSources()->first()?->getAlias()) ?? '')
                            ->required(),
                        Hidden::make('lookup_type'),
                        $optionsRepeater,
                    ]),
                    Tab::make('Visibility')
                        ->visible(
                            fn (): bool => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY)
                        )
                        ->schema([VisibilityComponent::make()]),
                    Tab::make(
                        __(
                            'custom-fields::custom-fields.field.form.validation.label'
                        )
                    )->schema([CustomFieldValidationComponent::make()]),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->contained(false),
        ];
    }
}
