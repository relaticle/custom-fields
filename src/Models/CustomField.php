<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Override;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Data\FieldTypeData;
use Relaticle\CustomFields\Data\Settings\CurrencyFieldSettingsData;
use Relaticle\CustomFields\Database\Factories\CustomFieldFactory;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\Enums\OptionCategory;
use Relaticle\CustomFields\Exceptions\RelationshipDefinitionDoesNotExistException;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\Concerns\Activable;
use Relaticle\CustomFields\Models\Concerns\HasFieldType;
use Relaticle\CustomFields\Models\Scopes\CustomFieldsActivableScope;
use Relaticle\CustomFields\Models\Scopes\SortOrderScope;
use Relaticle\CustomFields\Models\Scopes\TenantScope;
use Relaticle\CustomFields\Observers\CustomFieldObserver;
use Relaticle\CustomFields\QueryBuilders\CustomFieldQueryBuilder;

/**
 * @property string $name
 * @property string $code
 * @property string $type
 * @property string $entity_type
 * @property Collection<array-key, mixed> $validation_rules
 * @property CustomFieldSettingsData $settings
 * @property int $sort_order
 * @property bool $active
 * @property bool $system_defined
 * @property FieldTypeData $typeData
 * @property CustomFieldWidth $width
 * @property-read ?CustomFieldSection $section
 *
 * @method static CustomFieldQueryBuilder<static> query()
 * @method static CustomFieldQueryBuilder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static CustomFieldQueryBuilder<static> whereIn($column, $values, $boolean = 'and', $not = false)
 * @method static CustomFieldQueryBuilder<static> active()
 * @method static CustomFieldQueryBuilder<static> visibleInList()
 * @method static CustomFieldQueryBuilder<static> nonEncrypted()
 * @method static CustomFieldQueryBuilder<static> forEntity(string $model)
 * @method static CustomFieldQueryBuilder<static> forMorphEntity(string $entity)
 * @method static CustomFieldQueryBuilder<static> forType(string $type)
 * @method static CustomFieldQueryBuilder<static> withDeactivated(bool $withDeactivated = true)
 */
#[ScopedBy([TenantScope::class, SortOrderScope::class])]
#[ObservedBy(CustomFieldObserver::class)]
class CustomField extends Model
{
    use Activable;

    /** @use HasFactory<CustomFieldFactory> */
    use HasFactory;

    use HasFieldType;

    /**
     * @var array<string>
     */
    protected $guarded = ['id'];

    protected $attributes = [
        'width' => CustomFieldWidth::_100,
    ];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        if ($this->table === null) {
            $this->setTable(config('custom-fields.database.table_names.custom_fields'));
        }

        parent::__construct($attributes);
    }

    /**
     * Boot the soft deleting trait for a model.
     */
    public static function bootActivable(): void
    {
        static::addGlobalScope(new CustomFieldsActivableScope);
    }

    /**
     * @return CustomFieldQueryBuilder<self>
     */
    #[Override]
    public function newEloquentBuilder($query): CustomFieldQueryBuilder
    {
        return new CustomFieldQueryBuilder($query);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => 'string',
            'width' => CustomFieldWidth::class,
            'validation_rules' => AsCollection::class,
            'active' => 'boolean',
            'system_defined' => 'boolean',
            'settings' => CustomFieldSettingsData::class.':default',
        ];
    }

    /**
     * @return ?BelongsTo<CustomFieldSection, self>
     */
    public function section(): ?BelongsTo
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)) {
            return null;
        }

        /** @var BelongsTo<CustomFieldSection, self> */
        return $this->belongsTo(CustomFields::sectionModel(), 'custom_field_section_id');
    }

    /**
     * @return HasMany<CustomFieldValue, self>
     */
    public function values(): HasMany
    {
        /** @var HasMany<CustomFieldValue, self> */
        return $this->hasMany(CustomFields::valueModel());
    }

    /**
     * @return HasMany<CustomFieldOption, self>
     */
    public function options(): HasMany
    {
        /** @var HasMany<CustomFieldOption, self> */
        return $this->hasMany(CustomFields::optionModel())
            ->with('customField')
            ->orderBy('sort_order');
    }

    /**
     * @return EloquentCollection<int, CustomFieldOption>
     */
    public function optionsInCategory(OptionCategory $category): EloquentCollection
    {
        if ($this->relationLoaded('options')) {
            return $this->options
                ->filter(fn (CustomFieldOption $option): bool => $option->settings->category === $category)
                ->values();
        }

        return $this->options()->whereCategory($category)->get();
    }

    /**
     * The definition this field is a presentation slot of, from either end.
     */
    public function relationshipDefinition(): ?CustomFieldRelationship
    {
        $key = $this->getKey();

        // An unsaved field owns no slot, and its null key would read as whereNull and match
        // every one-way definition. Returning before once() keeps nothing memoised for it.
        if ($key === null) {
            return null;
        }

        // Only a field type that points at records is ever a slot, and every save asks each
        // field in turn, so the rest never pay for a definition lookup.
        if ($this->typeData?->requiresRelationship !== true) {
            return null;
        }

        return once(function () use ($key): ?CustomFieldRelationship {
            // The feature flag gates the two migrations and the lookup_type drop, not what a
            // field can read or write: a host that turns it off after migrating still has
            // definitions to find, and one that never migrated has no table to look in.
            if (! Schema::hasTable((string) config('custom-fields.database.table_names.custom_field_relationships'))) {
                return null;
            }

            return CustomFields::newRelationshipModel()
                ->newQuery()
                ->where(fn (Builder $query): Builder => $query
                    ->where('from_field_id', $key)
                    ->orWhere('to_field_id', $key))
                ->first();
        });
    }

    /**
     * The definition a record field writes one end of. A write has no sensible answer without
     * one, so it stops here; the surfaces that only render skip themselves instead.
     */
    public function relationshipDefinitionOrFail(): CustomFieldRelationship
    {
        return $this->relationshipDefinition()
            ?? throw RelationshipDefinitionDoesNotExistException::forField($this->code);
    }

    /**
     * Whether the field's type configures both ends of its relationship. The surfaces that
     * draw chips and confirm a move belong to that type; a one-way field keeps the plain ones
     * it has always had.
     */
    public function supportsPairing(): bool
    {
        return $this->typeData?->supportsPairing === true;
    }

    /**
     * The entity this field points at: the far end of its relationship definition. A field
     * that is not a relationship slot points nowhere.
     */
    public function targetEntityType(): ?string
    {
        return $this->relationshipDefinition()?->targetEntityTypeFor($this);
    }

    /**
     * Cardinality owns multiplicity for a record field: allow_multiple describes a value row,
     * and a relationship slot has none.
     */
    public function allowsMultipleRecords(): bool
    {
        $definition = $this->relationshipDefinition();

        if (! $definition instanceof CustomFieldRelationship) {
            return $this->settings->allow_multiple;
        }

        return $definition->directionFor($this) === CustomFieldRelationship::DIRECTION_TO
            ? ! $definition->cardinality->toSideIsSingle()
            : ! $definition->cardinality->fromSideIsSingle();
    }

    /**
     * @return Attribute<?FieldTypeData, never>
     */
    public function typeData(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes): ?FieldTypeData => CustomFieldsType::getFieldType($attributes['type'])
        );
    }

    /**
     * Determine if the model instance is user defined.
     */
    public function isSystemDefined(): bool
    {
        return $this->system_defined === true;
    }

    /**
     * A relationship slot keeps no value row, so what stands in the way of deleting it is
     * an active edge on its definition.
     */
    public function hasValues(): bool
    {
        $definition = $this->relationshipDefinition();

        if (! $definition instanceof CustomFieldRelationship) {
            return $this->values()->exists();
        }

        return CustomFields::newLinkModel()
            ->newQuery()
            ->where('relationship_id', $definition->getKey())
            ->whereNull('active_until')
            ->exists();
    }

    /**
     * Deactivate the field if it's not system-defined.
     */
    public function deactivate(): bool
    {
        if ($this->isSystemDefined()) {
            return false;
        }

        // Call the trait's deactivate logic directly
        if ($this->fireModelEvent('deactivating') === false) {
            return false;
        }

        $this->{$this->getActiveColumn()} = false;

        $result = $this->save();

        $this->fireModelEvent('deactivated', false);

        return $result;
    }

    public function getValueColumn(): string
    {
        return CustomFields::newValueModel()::getValueColumn($this->type);
    }

    public function getFieldName(): string
    {
        return 'custom_fields.'.$this->code;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->settings->additional, $key, $default);
    }

    public function getCurrencySettings(): CurrencyFieldSettingsData
    {
        $additional = $this->settings->additional ?? [];

        // Legacy fallback: decimal_places may live in validation_rules
        if (! isset($additional['decimal_places']) && $this->validation_rules?->has('decimal_places')) { // @phpstan-ignore nullsafe.neverNull
            $additional['decimal_places'] = $this->validation_rules->get('decimal_places');
        }

        // Apply config default for currency_code
        if (! isset($additional['currency_code'])) {
            $additional['currency_code'] = config('custom-fields.currency.default_code', 'USD');
        }

        return CurrencyFieldSettingsData::fromAdditional($additional);
    }

    public function getDecimalPlaces(): int
    {
        return $this->getCurrencySettings()->decimalPlaces;
    }

    public function getCurrencyCode(): string
    {
        return $this->getCurrencySettings()->currencyCode;
    }

    public function getCurrencyDisplayType(): string
    {
        return $this->getCurrencySettings()->displayType;
    }
}
