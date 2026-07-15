<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Schemas;

use Closure;
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
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Forms\Components\VisibilityComponent;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\TenantContextService;

class SectionForm implements FormInterface, SectionFormInterface
{
    private static string $entityType;

    /** @var ?Closure(Unique, Get):Unique */
    private static ?Closure $modifyUniqueRuleUsing = null;

    /**
     * The section being edited, handed to the conditional-visibility picker so it can be
     * scoped to the section's parent form. Null (e.g. on section create) applies no scope.
     */
    private static ?CustomFieldSection $visibilityScopeSection = null;

    /** @var array<int, Closure(array<int, Component>, string): array<int, Component>> */
    private static array $schemaExtensions = [];

    public static function entityType(string $entityType): self
    {
        self::$entityType = $entityType;
        self::$modifyUniqueRuleUsing = null;
        self::$visibilityScopeSection = null;

        return new self;
    }

    public function modifyUniqueRuleUsing(Closure $callback): self
    {
        self::$modifyUniqueRuleUsing = $callback;

        return $this;
    }

    public function scopeVisibilityToSection(?CustomFieldSection $section): self
    {
        self::$visibilityScopeSection = $section;

        return $this;
    }

    /**
     * Register a callback that can append to or modify the section form schema.
     * Applies to both the create and edit section modals. The callback receives
     * the current schema and the section's entity type. Register once (e.g. from
     * a service provider); extensions persist until flushed.
     *
     * @param  Closure(array<int, Component>, string): array<int, Component>  $callback
     */
    public static function extendSchemaUsing(Closure $callback): void
    {
        self::$schemaExtensions[] = $callback;
    }

    /**
     * Clear all registered schema extensions. Primarily for testing.
     */
    public static function flushSchemaExtensions(): void
    {
        self::$schemaExtensions = [];
    }

    private static function buildUniqueRule(Unique $rule, Get $get): Unique
    {
        $rule = $rule->when(
            FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY),
            fn (Unique $rule) => $rule->where(
                config('custom-fields.database.column_names.tenant_foreign_key'),
                TenantContextService::getCurrentTenantId()
            )
        )->where('entity_type', self::$entityType);

        if (self::$modifyUniqueRuleUsing instanceof Closure) {
            return (self::$modifyUniqueRuleUsing)($rule, $get);
        }

        return $rule;
    }

    /**
     * @return array<int, Component>
     */
    public static function schema(): array
    {
        $schema = [
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
                        ignorable: fn (TextInput $component): ?CustomFieldSection => ($record = $component->getRecord()) instanceof CustomFieldSection ? $record : null,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => self::buildUniqueRule($rule, $get),
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
                    ->columnSpan(
                        fn (): int => FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE) ? 12 : 6
                    ),
                TextInput::make('code')
                    ->label(
                        __('custom-fields::custom-fields.section.form.code')
                    )
                    ->required(
                        fn (): bool => ! FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE)
                    )
                    ->alphaDash()
                    ->maxLength(50)
                    ->visible(
                        fn (): bool => ! FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE)
                    )
                    ->unique(
                        table: CustomFields::sectionModel(),
                        column: 'code',
                        ignorable: fn (TextInput $component): ?CustomFieldSection => ($record = $component->getRecord()) instanceof CustomFieldSection ? $record : null,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => self::buildUniqueRule($rule, $get),
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
                Select::make('width')
                    ->label(__('custom-fields::custom-fields.section.form.width'))
                    ->options(CustomFieldWidth::class)
                    ->default(CustomFieldWidth::_100->value)
                    ->selectablePlaceholder(false)
                    ->formatStateUsing(fn (mixed $state): string => match (true) {
                        $state instanceof CustomFieldWidth => $state->value,
                        is_string($state) && $state !== '' => $state,
                        default => CustomFieldWidth::_100->value,
                    })
                    ->visible(fn (Get $get): bool => FeatureManager::isEnabled(CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL)
                        && in_array($get('type'), [
                            CustomFieldSectionType::SECTION,
                            CustomFieldSectionType::FIELDSET,
                        ], true))
                    ->columnSpan(6),
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
                    ->maxLength(config('custom-fields.sections.description_max_length', 255))
                    ->nullable()
                    ->columnSpan(12),
                ...self::visibilitySchema(),
            ]),
        ];

        foreach (self::$schemaExtensions as $extension) {
            $schema = $extension($schema, self::$entityType);
        }

        return $schema;
    }

    /**
     * @return array<int, Component>
     */
    private static function visibilitySchema(): array
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)) {
            return [];
        }

        return [
            VisibilityComponent::makeForSection(self::$entityType, self::$visibilityScopeSection),
        ];
    }
}
