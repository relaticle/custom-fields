<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Database\Factories\CustomFieldLinkFactory;
use Relaticle\CustomFields\Models\Scopes\TenantScope;

/**
 * @property int $id
 * @property int $relationship_id
 * @property string $from_entity_type
 * @property int|string $from_entity_id
 * @property string $to_entity_type
 * @property int|string $to_entity_id
 * @property ?int $sort_order
 * @property CarbonInterface $active_from
 * @property ?CarbonInterface $active_until
 * @property ?string $created_by_type
 * @property int|string|null $created_by_id
 * @property string $source
 * @property ?float $confidence
 * @property CustomFieldRelationship $relationship
 */
#[ScopedBy([TenantScope::class])]
class CustomFieldLink extends Model
{
    /** @use HasFactory<CustomFieldLinkFactory> */
    use HasFactory;

    /**
     * The partial unique index the writer translates into a friendly conflict error.
     */
    public const string ACTIVE_EDGE_INDEX = 'cf_links_active_edge_unique';

    public const string SOURCE_USER = 'user';

    public const string SOURCE_IMPORT = 'import';

    public const string SOURCE_MIGRATION = 'migration';

    public const string SOURCE_AI = 'ai_inferred';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        if ($this->table === null) {
            $this->setTable(
                config('custom-fields.database.table_names.custom_field_links')
            );
        }

        parent::__construct($attributes);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active_from' => 'datetime',
            'active_until' => 'datetime',
            'confidence' => 'float',
        ];
    }

    /**
     * @return BelongsTo<CustomFieldRelationship, $this>
     */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(CustomFields::relationshipModel(), 'relationship_id');
    }

    /**
     * The relation name has to be the method name: an eager load initialises the relation
     * under that name, and morphTo would otherwise fill a differently named one, leaving
     * every eager-loaded end null while lazy access works.
     *
     * @return MorphTo<Model, $this>
     */
    public function fromEntity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'from_entity_type', 'from_entity_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function toEntity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'to_entity_type', 'to_entity_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function createdBy(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'created_by_type', 'created_by_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('active_until');
    }

    /**
     * An edge is closed, never deleted, so the history stays queryable.
     */
    public function close(CarbonInterface $at): void
    {
        $this->active_until = $at;

        $this->save();
    }
}
