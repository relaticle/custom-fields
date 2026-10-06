<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Schemas;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;
use Relaticle\CustomFields\Contracts\ValidationCapabilityInterface;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\CustomFieldOptionSettingsData;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\DescriptionPosition;
use Relaticle\CustomFields\Enums\OptionCategory;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\StatusFieldType;
use Relaticle\CustomFields\Filament\Management\Forms\Components\RelationshipConfigurator;
use Relaticle\CustomFields\Filament\Management\Forms\Components\TypeField;
use Relaticle\CustomFields\Filament\Management\Forms\Components\VisibilityComponent;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Support\FieldFormConfiguration;
use Relaticle\CustomFields\Support\OptionNameParser;
use Relaticle\CustomFields\Support\ViewFlavor;

final class FieldForm implements FormInterface
{
    /** @var ?Closure(?CustomFieldSection): ?Closure */
    private static ?Closure $uniqueNameRuleModifierResolver = null;

    /** @var ?Closure(?CustomFieldSection): ?Closure */
    private static ?Closure $uniqueCodeRuleModifierResolver = null;

    /** @var array<int, Closure(array<int, Component>, ?CustomFieldSection): array<int, Component>> */
    private static array $schemaExtensions = [];

    /**
     * Register a resolver that scopes the field-name uniqueness rule beyond the default
     * entity-type (+ tenant) scope. The resolver receives the section the field belongs to
     * (the create target, or the edited field's section) and returns a rule modifier, or
     * null for no extra scope. Lets a consumer allow the same field name across separate
     * parent forms while still preventing duplicates within one form. Register once.
     *
     * @param  ?Closure(?CustomFieldSection $section): ?Closure  $resolver
     */
    public static function resolveUniqueRuleModifierUsing(?Closure $resolver): void
    {
        self::$uniqueNameRuleModifierResolver = $resolver;
    }

    /**
     * Register a resolver that scopes the field-code uniqueness rule beyond the default
     * entity-type (+ tenant) scope. Same contract as resolveUniqueRuleModifierUsing(), kept
     * as a separate hook because code and name typically need different scoping: name is a
     * cosmetic label a consumer may want unique per parent form only, while code is a
     * stable identity other systems (e.g. reporting) key off of, so its scope is usually
     * "everything except a defined set of related forms" rather than "this one form".
     * Register once.
     *
     * @param  ?Closure(?CustomFieldSection $section): ?Closure  $resolver
     */
    public static function resolveUniqueCodeRuleModifierUsing(?Closure $resolver): void
    {
        self::$uniqueCodeRuleModifierResolver = $resolver;
    }

    /**
     * @param  Closure(array<int, Component>, ?CustomFieldSection): array<int, Component>  $callback
     */
    public static function extendSchemaUsing(Closure $callback): void
    {
        self::$schemaExtensions[] = $callback;
    }

    public static function flushSchemaExtensions(): void
    {
        self::$schemaExtensions = [];
    }

    private static function resolveUniqueNameRuleModifier(?CustomFieldSection $section): ?Closure
    {
        if (self::$uniqueNameRuleModifierResolver instanceof Closure) {
            return (self::$uniqueNameRuleModifierResolver)($section);
        }

        return null;
    }

    private static function resolveUniqueCodeRuleModifier(?CustomFieldSection $section): ?Closure
    {
        if (self::$uniqueCodeRuleModifierResolver instanceof Closure) {
            return (self::$uniqueCodeRuleModifierResolver)($section);
        }

        return null;
    }

    /**
     * Both link types configure a relationship definition, and they ask for different things:
     * a record field points one way, so it asks where and how many; a relationship field owns
     * both ends, so it gets the configurator. Only one frame is ever visible.
     */
    private static function recordConfiguration(): Group
    {
        return Group::make()
            ->columnSpanFull()
            ->schema([
                self::oneWayConfiguration(),
                self::pairedConfiguration(),
            ]);
    }

    /**
     * The record type's configuration, unchanged since 3.x: the entity it links to, locked
     * once the field exists, and whether it holds more than one record. Cardinality carries
     * the answer, so the toggle is what the user reads and the definition is what it writes.
     */
    private static function oneWayConfiguration(): Fieldset
    {
        return Fieldset::make(__('custom-fields::custom-fields.field.form.record.label'))
            ->columns(2)
            ->columnSpanFull()
            ->visible(fn (Get $get): bool => self::isOneWayRecordField($get('type')))
            ->schema([
                self::targetEntitySelect(),
                Toggle::make('relationship.allow_multiple')
                    ->inline()
                    ->live()
                    ->label(__('custom-fields::custom-fields.field.form.allow_multiple'))
                    ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.allow_multiple_help'))
                    ->default(false),
                // The toggle answers for this field's own end, so the cardinality it asks
                // for keeps whatever the other end already holds.
                self::keepFirstConfirmation(function (Get $get, ?CustomField $record): ?RelationshipCardinality {
                    $definition = $record?->relationshipDefinition();

                    if (! $record instanceof CustomField || ! $definition instanceof CustomFieldRelationship) {
                        return null;
                    }

                    return $definition->orientCardinality($record, $definition->cardinality)
                        ->fromSideHolds($get('relationship.allow_multiple') === true);
                }),
            ]);
    }

    /**
     * The relationship type's configuration: where the field points, how many records each
     * end holds, and the field rendering the other end.
     */
    private static function pairedConfiguration(): Component
    {
        $view = ViewFlavor::view(UiSurface::RelationshipConfigurator);
        $components = self::recordConfigurationComponents();

        // The flavor decides the frame the same children are placed in, and nothing else:
        // every closure below is shared, so the two presentations cannot drift apart.
        if ($view === null) {
            return Fieldset::make(__('custom-fields::custom-fields.field.form.record.label'))
                ->columns(2)
                ->columnSpanFull()
                ->visible(fn (Get $get): bool => self::isPairedField($get('type')))
                ->schema($components);
        }

        return RelationshipConfigurator::make()
            ->view($view)
            ->columnSpanFull()
            ->visible(fn (Get $get): bool => self::isPairedField($get('type')))
            ->schema($components);
    }

    /**
     * The machine code is derived from the name and rarely touched by hand, so it sits behind
     * a disclosure instead of beside the name it comes from. Uniqueness is scoped per entity
     * type, and per tenant when the host is multi-tenant.
     */
    private static function advancedDisclosure(?Closure $uniqueCodeRuleModifier): Section
    {
        return Section::make(__('custom-fields::custom-fields.field.form.advanced'))
            ->description(__('custom-fields::custom-fields.field.form.advanced_description'))
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->collapsible()
            ->collapsed()
            ->columnSpanFull()
            ->visible(fn (): bool => ! FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE))
            ->schema([
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
                        modifyRuleUsing: function (Unique $rule, Get $get) use ($uniqueCodeRuleModifier): Unique {
                            $rule = $rule
                                ->where('entity_type', $get('entity_type'))
                                ->when(
                                    FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY),
                                    fn (Unique $rule) => $rule->where(
                                        config('custom-fields.database.column_names.tenant_foreign_key'),
                                        TenantContextService::getCurrentTenantId()
                                    )
                                );

                            if ($uniqueCodeRuleModifier instanceof Closure) {
                                return $uniqueCodeRuleModifier($rule, $get);
                            }

                            return $rule;
                        }
                    )
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('code', Str::of($state)->slug('_')->toString());
                    }),
            ]);
    }

    /**
     * A vocabulary is pasted, not clicked in one option at a time. The rows are appended
     * through the repeater's own state so tenant stamping and sort_order keep working, which
     * is why nothing here writes an option model.
     */
    private static function pasteOptionsAction(): Action
    {
        return Action::make('pasteOptions')
            ->label(__('custom-fields::custom-fields.field.form.options.paste'))
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->link()
            ->modalHeading(__('custom-fields::custom-fields.field.form.options.paste_modal_heading'))
            ->modalSubmitActionLabel(__('custom-fields::custom-fields.field.form.options.paste_submit'))
            ->modalWidth(Width::Large)
            ->schema([
                Textarea::make('names')
                    ->label(__('custom-fields::custom-fields.field.form.options.paste_names'))
                    ->helperText(__('custom-fields::custom-fields.field.form.options.paste_names_help', [
                        'max' => OptionNameParser::MAX_NAMES,
                    ]))
                    ->rows(10)
                    ->required(),
            ])
            ->action(function (array $data, Repeater $component): void {
                $items = self::optionItems($component);

                $parsed = OptionNameParser::parse(
                    is_string($data['names'] ?? null) ? $data['names'] : null,
                    array_map(fn (array $item): mixed => $item['name'] ?? null, $items),
                );

                self::appendOptionNames($component, $parsed['names']);

                $body = __('custom-fields::custom-fields.field.form.options.pasted', [
                    'added' => count($parsed['names']),
                    'duplicates' => $parsed['duplicates'],
                ]);

                if ($parsed['truncated']) {
                    $body .= '. '.__('custom-fields::custom-fields.field.form.options.pasted_capped', [
                        'max' => OptionNameParser::MAX_NAMES,
                    ]);
                }

                Notification::make()
                    ->success()
                    ->title(__('custom-fields::custom-fields.field.form.options.paste_modal_heading'))
                    ->body($body)
                    ->send();
            });
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    private static function optionItems(Repeater $component): array
    {
        $items = [];

        foreach (Arr::wrap($component->getRawState()) as $key => $item) {
            $items[$key] = is_array($item) ? $item : [];
        }

        return $items;
    }

    /**
     * Mirrors the repeater's own add action: a key per row, then the child schema fills it.
     *
     * @param  list<string>  $names
     */
    private static function appendOptionNames(Repeater $component, array $names): void
    {
        if ($names === []) {
            return;
        }

        // A row opened and left blank fails the required name rule, and a pasted list has no
        // use for it.
        $items = array_filter(
            self::optionItems($component),
            fn (array $item): bool => filled($item['name'] ?? null),
        );

        $filled = [];

        foreach ($names as $name) {
            $uuid = $component->generateUuid();

            if ($uuid === null) {
                $items[] = [];
                $filled[array_key_last($items)] = $name;

                continue;
            }

            $items[$uuid] = [];
            $filled[$uuid] = $name;
        }

        $component->rawState($items);

        foreach ($filled as $key => $name) {
            $component->getChildSchema((string) $key)?->fill(['name' => $name]);
        }

        $component->callAfterStateUpdated();
    }

    /**
     * The entity the field links to. Both ends of a definition are locked once it exists, so
     * the select is read-only from the first save on.
     */
    private static function targetEntitySelect(): Select
    {
        return Select::make('relationship.target_entity_type')
            ->label(__('custom-fields::custom-fields.field.form.record.target'))
            ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.record.target_help'))
            ->options(Entities::getLookupOptions())
            ->default((Entities::asLookupSources()->first()?->getAlias()) ?? '')
            ->disabled(fn (?CustomField $record): bool => (bool) $record?->exists)
            ->required()
            ->live();
    }

    /**
     * A field that stops holding many records closes the edges that no longer fit, so the
     * narrowing is confirmed before it is saved. Each face reads the cardinality off its own
     * control, which is a toggle on one and a select on the other.
     *
     * @param  Closure(Get, ?CustomField): ?RelationshipCardinality  $cardinality
     */
    private static function keepFirstConfirmation(Closure $cardinality): Checkbox
    {
        return Checkbox::make('relationship.keep_first')
            ->label(__('custom-fields::custom-fields.field.form.record.keep_first'))
            ->helperText(__('custom-fields::custom-fields.field.form.record.keep_first_help'))
            ->columnSpanFull()
            ->accepted()
            ->default(false)
            ->visible(fn (Get $get, ?CustomField $record): bool => self::narrowsCardinality($record, $cardinality($get, $record)));
    }

    /**
     * @return array<int, Component>
     */
    private static function recordConfigurationComponents(): array
    {
        return [
            self::targetEntitySelect(),
            Select::make('relationship.cardinality')
                ->label(__('custom-fields::custom-fields.field.form.record.cardinality'))
                ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.record.cardinality_help'))
                ->options(fn (Get $get): array => self::cardinalityOptions($get('relationship.is_symmetric') === true))
                ->default(RelationshipCardinality::ManyToOne->value)
                ->required()
                ->live(),
            Toggle::make('relationship.is_symmetric')
                ->inline()
                ->live()
                ->label(__('custom-fields::custom-fields.field.form.record.is_symmetric'))
                ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.record.is_symmetric_help'))
                ->visible(fn (Get $get, ?CustomField $record): bool => $record?->exists !== true
                    && self::endsMatch($get('entity_type'), $get('relationship.target_entity_type')))
                ->default(false),
            TextInput::make('relationship.paired_field_name')
                ->label(__('custom-fields::custom-fields.field.form.record.paired_field_name'))
                ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.record.paired_field_name_help'))
                // A suggestion, never a value: filling it would turn every one-way field
                // into a paired one without the user asking for a second field.
                ->placeholder(fn (Get $get): ?string => self::pairedFieldNameSuggestion($get('entity_type')))
                ->maxLength(50)
                ->live(onBlur: true)
                ->disabled(fn (?CustomField $record): bool => (bool) $record?->exists)
                ->visible(fn (Get $get, ?CustomField $record): bool => $record?->exists === true
                    ? filled($get('relationship.paired_field_name'))
                    : $get('relationship.is_symmetric') !== true),
            Select::make('relationship.paired_section_id')
                ->label(__('custom-fields::custom-fields.field.form.record.paired_section'))
                ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.record.paired_section_help'))
                ->options(fn (Get $get): array => self::sectionOptions($get('relationship.target_entity_type')))
                ->required()
                // An entity with no section has nothing to choose, and the definition
                // service puts the paired field in a default one, so asking would only
                // block the save.
                ->visible(fn (Get $get, ?CustomField $record): bool => FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)
                    && $record?->exists !== true
                    && filled($get('relationship.paired_field_name'))
                    && $get('relationship.is_symmetric') !== true
                    && self::sectionOptions($get('relationship.target_entity_type')) !== []),
            self::keepFirstConfirmation(fn (Get $get, ?CustomField $record): ?RelationshipCardinality => RelationshipCardinality::tryFrom((string) $get('relationship.cardinality'))),
        ];
    }

    private static function pairedFieldNameSuggestion(mixed $entityType): ?string
    {
        if (! is_string($entityType) || $entityType === '') {
            return null;
        }

        return Entities::getEntity($entityType)?->getLabelPlural();
    }

    /**
     * The state the record configuration is filled from: an existing field reads its own
     * definition, from whichever end it renders.
     *
     * @return array<string, mixed>|null
     */
    public static function relationshipState(CustomField $field): ?array
    {
        $definition = $field->relationshipDefinition();

        if (! $definition instanceof CustomFieldRelationship) {
            return null;
        }

        $partner = match (true) {
            $definition->is_symmetric => null,
            $definition->directionFor($field) === CustomFieldRelationship::DIRECTION_FROM => $definition->toField,
            default => $definition->fromField,
        };

        return [
            'target_entity_type' => $field->targetEntityType(),
            'cardinality' => $definition->orientCardinality($field, $definition->cardinality)->value,
            'allow_multiple' => $field->allowsMultipleRecords(),
            'is_symmetric' => $definition->is_symmetric,
            'paired_field_name' => $partner?->name,
        ];
    }

    private static function isRelationshipField(mixed $type): bool
    {
        if (! is_string($type) || $type === '') {
            return false;
        }

        return CustomFieldsType::getFieldType($type)?->requiresRelationship === true;
    }

    /**
     * The one-way record type: it links records like the paired type does, and configures
     * neither a second field nor a cardinality of its own.
     */
    private static function isOneWayRecordField(mixed $type): bool
    {
        return self::isRelationshipField($type) && ! self::isPairedField($type);
    }

    private static function isPairedField(mixed $type): bool
    {
        if (! is_string($type) || $type === '') {
            return false;
        }

        return CustomFieldsType::getFieldType($type)?->supportsPairing === true;
    }

    /**
     * A symmetric relationship reads one field from both ends, so a cardinality that
     * constrains only one of them cannot describe it.
     *
     * @return array<string, string>
     */
    private static function cardinalityOptions(bool $isSymmetric): array
    {
        $cases = $isSymmetric
            ? [RelationshipCardinality::OneToOne, RelationshipCardinality::ManyToMany]
            : RelationshipCardinality::cases();

        $options = [];

        foreach ($cases as $case) {
            $options[$case->value] = $case->getLabel();
        }

        return $options;
    }

    private static function endsMatch(mixed $entityType, mixed $targetEntityType): bool
    {
        if (! is_string($entityType) || ! is_string($targetEntityType) || $entityType === '' || $targetEntityType === '') {
            return false;
        }

        $alias = Entities::getEntity($entityType)?->getAlias();

        // Two entity types the host has not registered are not the same entity, and a
        // relationship cannot be symmetric across an end that resolves to nothing.
        return $alias !== null && $alias === Entities::getEntity($targetEntityType)?->getAlias();
    }

    /**
     * The sections of the entity the paired field lands on. Sections are what the activable
     * scope reads, so a paired field without one would never render.
     *
     * @return array<string, string>
     */
    private static function sectionOptions(mixed $entityType): array
    {
        if (! is_string($entityType) || $entityType === '') {
            return [];
        }

        $entity = Entities::getEntity($entityType);
        $candidates = array_values(array_unique(array_filter([$entityType, $entity?->getAlias(), $entity?->getModelClass()])));

        $options = [];

        foreach (CustomFields::newSectionModel()->newQuery()->whereIn('entity_type', $candidates)->orderBy('sort_order')->get() as $section) {
            $options[(string) $section->getKey()] = (string) $section->name;
        }

        return $options;
    }

    private static function narrowsCardinality(?CustomField $record, ?RelationshipCardinality $target): bool
    {
        if (! $record instanceof CustomField || ! $record->exists || ! $target instanceof RelationshipCardinality) {
            return false;
        }

        $definition = $record->relationshipDefinition();

        if (! $definition instanceof CustomFieldRelationship) {
            return false;
        }

        return $definition->orientCardinality($record, $definition->cardinality)->narrows($target);
    }

    /**
     * Disable field when editing a system-defined custom field.
     */
    private static function disabledForSystemFields(): Closure
    {
        return fn (?CustomField $record): bool => $record?->isSystemDefined() ?? false;
    }

    // The options repeater pairs each table column with the schema component in the same
    // position, so the category header and the category select answer one question.
    private static function showsOptionCategories(mixed $type): bool
    {
        if (! is_string($type) || $type === '') {
            return false;
        }

        return CustomFieldsType::getFieldType($type)?->carriesOptionCategories === true;
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
                /** @var ValidationCapabilityInterface $capability */
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
     * A create action that fills the form hydrates that state instead of the schema's own
     * defaults, so the entity type arrives here rather than through fillForm().
     *
     * @return array<int, Component>
     */
    public static function schema(bool $withOptionsRelationship = true, ?CustomFieldSection $section = null, ?string $entityType = null): array
    {
        $uniqueNameRuleModifier = self::resolveUniqueNameRuleModifier($section);
        $uniqueCodeRuleModifier = self::resolveUniqueCodeRuleModifier($section);

        $optionsRepeater = Repeater::make('options')
            ->table(fn (Get $get): array => [
                TableColumn::make('Color')->width('150px')->hiddenHeaderLabel(),
                TableColumn::make('Name')->hiddenHeaderLabel(),
                ...(self::showsOptionCategories($get('type')) ? [
                    TableColumn::make(__('custom-fields::custom-fields.field.form.options.category'))
                        ->width('200px')
                        ->hiddenHeaderLabel(),
                ] : []),
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
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (blank($value)) {
                                return;
                            }

                            $hasDuplicate = collect(Arr::wrap($get('../../options')))
                                ->pluck('name')
                                ->filter()
                                ->map(fn (string $name): string => mb_strtolower($name))
                                ->duplicates()
                                ->contains(mb_strtolower($value));

                            if ($hasDuplicate) {
                                $fail(__('validation.distinct'));
                            }
                        },
                    ]),
                Select::make('settings.category')
                    ->options(OptionCategory::class)
                    ->placeholder(__('custom-fields::custom-fields.field.form.options.category_placeholder'))
                    ->visible(fn (Get $get): bool => self::showsOptionCategories($get('../../type'))),
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
            // A blank row the user never typed fails the name rule on the one type whose
            // options are optional, so the first row comes from the add action instead.
            ->defaultItems(0)
            ->hintAction(self::pasteOptionsAction())
            ->addActionLabel(
                __('custom-fields::custom-fields.field.form.options.add')
            )
            ->columnSpanFull()
            ->label(__('custom-fields::custom-fields.field.form.options.label'))
            ->visible(
                fn (Get $get): bool => $get('type') !== null
                    && CustomFieldsType::getFieldType($get('type'))->dataType->isChoiceField()
                    && ! CustomFieldsType::getFieldType($get('type'))->withoutUserOptions
                    && ! CustomFieldsType::getFieldType($get('type'))->requiresRelationship
            )
            ->mutateRelationshipDataBeforeCreateUsing(function (
                array $data
            ): array {
                if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                    $data[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
                }

                return $data;
            })
            ->mutateRelationshipDataBeforeSaveUsing(function (array $data, Model $record): array {
                // A hidden column is never dehydrated, so a submitted item carries only the
                // settings the editor showed and would rewrite the row without the rest.
                $stored = $record->getAttribute('settings');
                $submitted = $data['settings'] ?? null;

                $data['settings'] = [
                    ...($stored instanceof CustomFieldOptionSettingsData ? $stored->toArray() : []),
                    ...(is_array($submitted) ? $submitted : []),
                ];

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
                    fn (): mixed => $entityType ?? request(
                        'entityType',
                        (Entities::withCustomFields()->first()?->getAlias()) ?? ''
                    )
                ),
            Grid::make()
                ->columns(2)
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
                                    $record->type ?? CustomFieldsType::toCollection()->selectable()->first()->key
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
                            modifyRuleUsing: function (Unique $rule, Get $get) use ($uniqueNameRuleModifier): Unique {
                                $rule = $rule
                                    ->where('entity_type', $get('entity_type'))
                                    ->when(
                                        FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY),
                                        fn (Unique $rule) => $rule->where(
                                            config('custom-fields.database.column_names.tenant_foreign_key'),
                                            TenantContextService::getCurrentTenantId()
                                        )
                                    );

                                if ($uniqueNameRuleModifier instanceof Closure) {
                                    return $uniqueNameRuleModifier($rule, $get);
                                }

                                return $rule;
                            }
                        )
                        ->afterStateUpdated(function (Get $get, Set $set, ?CustomField $record, ?string $old, ?string $state): void {
                            // On a persisted field the code is an identity, not a label:
                            // stored values, report columns and visibility conditions all
                            // key on it, and a field cloned onto a new form version shares
                            // it with the original. Renaming must therefore never rewrite
                            // it — only derive the code while the field is being created.
                            if ($record instanceof CustomField) {
                                return;
                            }

                            $old ??= '';
                            $state ??= '';

                            if (($get('code') ?? '') !== Str::of($old)->slug('_')->toString()) {
                                return;
                            }

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
            ...self::settingsSchema(),

            // Dynamic type-specific settings from field type definition
            ...self::getTypeSettingsSchema(),

        ];

        $generalSchema[] = self::recordConfiguration();

        $generalSchema[] = $optionsRepeater;

        $generalSchema[] = self::advancedDisclosure($uniqueCodeRuleModifier);

        foreach (self::$schemaExtensions as $extension) {
            $generalSchema = $extension($generalSchema, $section);
        }

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
            )->schema([VisibilityComponent::make($section)]);
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

    /**
     * A field whose options a user names and colors. Every other type stores the flag
     * without anything reading it, so the form neither offers it nor writes it there.
     */
    private static function carriesOptionColors(mixed $type): bool
    {
        return FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS)
            && in_array((string) $type, ['select', StatusFieldType::KEY, 'multi-select', 'tags-input'], true);
    }

    /**
     * A host narrows what the form asks about through custom-fields.field_form.settings.
     * A setting it leaves out is not removed: an edit merges over what the field already
     * stores, and a new field takes the default its data object carries.
     *
     * The full set keeps its own bordered group. A narrowed one sits in the form grid
     * instead: most of what is left is gated on the field type, and a group that empties
     * itself on a Text field is a box with a heading and nothing under it.
     *
     * @return list<Component>
     */
    private static function settingsSchema(): array
    {
        $components = self::settingsComponents();

        $offered = array_filter(
            $components,
            fn (string $setting): bool => FieldFormConfiguration::offers($setting),
            ARRAY_FILTER_USE_KEY,
        );

        $schema = match (true) {
            $offered === [] => [],
            $offered === $components => [
                Fieldset::make(__('custom-fields::custom-fields.field.form.settings'))
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema(array_merge(...array_values($offered))),
            ],
            default => array_merge(...array_values($offered)),
        };

        // Colors nobody can switch on are colors the badges, filters and record pages
        // never draw, so a form that stops asking turns them on instead of off.
        if (! FieldFormConfiguration::offers('enable_option_colors')) {
            $schema[] = Toggle::make('settings.enable_option_colors')
                ->hidden()
                ->dehydratedWhenHidden()
                ->dehydrated(fn (Get $get): bool => self::carriesOptionColors($get('type')))
                ->afterStateHydrated(function (Toggle $component): void {
                    $component->state(true);
                });
        }

        return $schema;
    }

    /**
     * Every optional setting the form can offer, keyed by the name a host narrows it
     * by. max_values rides with allow_multiple: it is the ceiling on that toggle and
     * only ever renders beside it.
     *
     * @return array<string, list<Component>>
     */
    private static function settingsComponents(): array
    {
        return [
            'visible_in_list' => [
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
            ],
            'visible_in_view' => [
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
            ],
            'list_toggleable_hidden' => [
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
            ],
            'searchable' => [
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
            ],
            'encrypted' => [
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
            ],
            'enable_option_colors' => [
                Toggle::make('settings.enable_option_colors')
                    ->inline()
                    ->live()
                    ->label(
                        __(
                            'custom-fields::custom-fields.field.form.enable_option_colors'
                        )
                    )
                    ->hintIcon(Heroicon::OutlinedQuestionMarkCircle, tooltip: __('custom-fields::custom-fields.field.form.enable_option_colors_help'))
                    ->visible(fn (Get $get): bool => self::carriesOptionColors($get('type'))),
            ],
            'allow_multiple' => [
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
                            $fieldType->requiresRelationship !== true &&
                            $get('settings.allow_multiple') === true;
                    }),
            ],
            'unique_per_entity_type' => [
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
            ],
        ];
    }
}
