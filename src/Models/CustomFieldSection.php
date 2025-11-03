<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\CustomFieldSectionSettingsData;
use Relaticle\CustomFields\Database\Factories\CustomFieldSectionFactory;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Models\Concerns\Activable;
use Relaticle\CustomFields\Models\Scopes\SortOrderScope;
use Relaticle\CustomFields\Models\Scopes\TenantScope;
use Relaticle\CustomFields\Observers\CustomFieldSectionObserver;

/**
 * @property string $name
 * @property string $code
 * @property string $description
 * @property CustomFieldSectionType $type
 * @property string $entity_type
 * @property string $lookup_type
 * @property CustomFieldSectionSettingsData $settings
 * @property int $sort_order
 * @property bool $active
 * @property bool $system_defined
 *
 * @method static Builder<static> active()
 * @method static Builder<static> withDeactivated(bool $withDeactivated = true)
 * @method static Builder<static> withoutDeactivated()
 * @method static Builder<static> forEntityType(string $model)
 */
#[ScopedBy([TenantScope::class, SortOrderScope::class])]
#[ObservedBy(CustomFieldSectionObserver::class)]
class CustomFieldSection extends Model
{
    use Activable;

    /** @use HasFactory<CustomFieldSectionFactory> */
    use HasFactory;

    /**
     * @var array<string>
     */
    protected $guarded = [];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        if ($this->table === null) {
            $this->setTable(config('custom-fields.database.table_names.custom_field_sections'));
        }

        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'type' => CustomFieldSectionType::class,
            'settings' => CustomFieldSectionSettingsData::class.':default',
            'system_defined' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CustomField, self>
     */
    public function fields(): HasMany
    {
        /** @var HasMany<CustomField, self> */
        return $this->hasMany(CustomFields::customFieldModel());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForEntityType(Builder $query, string $model): Builder
    {
        return $query->where('entity_type', (Entities::getEntity($model)?->getAlias()) ?? $model);
    }

    /**
     * Determine if the model instance is user defined.
     */
    public function isSystemDefined(): bool
    {
        return $this->system_defined === true;
    }

    /**
     * Determine if the section contains any system-defined fields.
     */
    public function hasSystemDefinedFields(): bool
    {
        return $this->fields()->withDeactivated()->where('system_defined', true)->exists();
    }
}
