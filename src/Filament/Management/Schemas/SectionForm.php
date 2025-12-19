<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Services\TenantContextService;

class SectionForm implements FormInterface, SectionFormInterface
{
    private static string $entityType;

    public static function entityType(string $entityType): self
    {
        self::$entityType = $entityType;

        return new self;
    }

    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        return [
            Grid::make(12)->schema([
                TextInput::make('name')
                    ->label(
                        __('custom-fields::custom-fields.section.form.name')
                    )
                    ->required()
                    ->live(onBlur: true)
                    ->maxLength(50)
                    ->unique(
                        table: CustomFields::sectionModel(),
                        column: 'name',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                            ->when(
                                FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY),
                                fn (Unique $rule) => $rule->where(
                                    config(
                                        'custom-fields.database.column_names.tenant_foreign_key'
                                    ),
                                    TenantContextService::getCurrentTenantId()
                                )
                            )
                            ->where('entity_type', self::$entityType)
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

                        $set('code', Str::of($state)->slug('_')->toString());
                    })
                    ->columnSpan(6),
                TextInput::make('code')
                    ->label(
                        __('custom-fields::custom-fields.section.form.code')
                    )
                    ->required()
                    ->alphaDash()
                    ->maxLength(50)
                    ->unique(
                        table: CustomFields::sectionModel(),
                        column: 'code',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule
                            ->when(
                                FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY),
                                fn (Unique $rule) => $rule->where(
                                    config(
                                        'custom-fields.database.column_names.tenant_foreign_key'
                                    ),
                                    TenantContextService::getCurrentTenantId()
                                )
                            )
                            ->where('entity_type', self::$entityType)
                    )
                    ->afterStateUpdated(function (
                        Set $set,
                        ?string $state
                    ): void {
                        $set('code', Str::of($state)->slug('_')->toString());
                    })
                    ->columnSpan(6),
                Select::make('type')
                    ->label(
                        __('custom-fields::custom-fields.section.form.type')
                    )
                    ->live()
                    ->default(CustomFieldSectionType::SECTION->value)
                    ->options(CustomFieldSectionType::class)
                    ->required()
                    ->columnSpan(12),
                Textarea::make('description')
                    ->label(
                        __(
                            'custom-fields::custom-fields.section.form.description'
                        )
                    )
                    ->live()
                    ->visible(
                        fn (Get $get): bool => $get('type') ===
                            CustomFieldSectionType::SECTION
                    )
                    ->maxLength(255)
                    ->nullable()
                    ->columnSpan(12),
            ]),
        ];
    }
}
