<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models;

use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Database\Factories\CustomFieldRelationshipFactory;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\Scopes\TenantScope;
use Relaticle\CustomFields\Observers\CustomFieldRelationshipObserver;

/**
 * @property int $id
 * @property string $code
 * @property string $from_entity_type
 * @property string $to_entity_type
 * @property RelationshipCardinality $cardinality
 * @property ?int $from_field_id
 * @property ?int $to_field_id
 * @property bool $is_symmetric
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[ScopedBy([TenantScope::class])]
#[ObservedBy(CustomFieldRelationshipObserver::class)]
class CustomFieldRelationship extends Model
{
    /** @use HasFactory<CustomFieldRelationshipFactory> */
    use HasFactory;

    public const string DIRECTION_FROM = 'from';

    public const string DIRECTION_TO = 'to';

    public const string DIRECTION_BOTH = 'both';

    protected $guarded = [];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        if ($this->table === null) {
            $this->setTable(
                config('custom-fields.database.table_names.custom_field_relationships')
            );
        }

        parent::__construct($attributes);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cardinality' => RelationshipCardinality::class,
            'is_symmetric' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CustomField, $this>
     */
    public function fromField(): BelongsTo
    {
        return $this->belongsTo(CustomFields::customFieldModel(), 'from_field_id');
    }

    /**
     * @return BelongsTo<CustomField, $this>
     */
    public function toField(): BelongsTo
    {
        return $this->belongsTo(CustomFields::customFieldModel(), 'to_field_id');
    }

    /**
     * @return self::DIRECTION_FROM|self::DIRECTION_TO
     */
    public function directionFor(CustomField $field): string
    {
        $key = $field->getKey();

        // An empty slot is null on both sides, so a keyless field would match the from slot
        // of every one-way definition.
        if ($key !== null) {
            if ($key === $this->from_field_id) {
                return self::DIRECTION_FROM;
            }

            if ($key === $this->to_field_id) {
                return self::DIRECTION_TO;
            }
        }

        throw new InvalidArgumentException(sprintf('Field [%s] does not belong to relationship [%s].', $key ?? 'unsaved', $this->code));
    }

    /**
     * The entity the given slot points at: the end it does not sit on.
     */
    public function targetEntityTypeFor(CustomField $field): string
    {
        return $this->directionFor($field) === self::DIRECTION_TO
            ? $this->from_entity_type
            : $this->to_entity_type;
    }

    /**
     * A cardinality in the given slot's own terms: a to-end field reads the definition
     * backwards, so what is stored as many_to_one holds many records there. The transform is
     * its own inverse, so the same call converts that field's answer back for storage.
     */
    public function orientCardinality(CustomField $field, RelationshipCardinality $cardinality): RelationshipCardinality
    {
        return $this->directionFor($field) === self::DIRECTION_TO
            ? $cardinality->inverse()
            : $cardinality;
    }

    /**
     * A symmetric definition renders one field that reads both ends of its edges.
     *
     * @return self::DIRECTION_FROM|self::DIRECTION_TO|self::DIRECTION_BOTH
     */
    public function readDirectionFor(CustomField $field): string
    {
        return $this->is_symmetric
            ? self::DIRECTION_BOTH
            : $this->directionFor($field);
    }

    /**
     * Writes always name one end: a symmetric edge is stored from the canonical side, so
     * both of its slots write as the from end.
     *
     * @return self::DIRECTION_FROM|self::DIRECTION_TO
     */
    public function writeDirectionFor(CustomField $field): string
    {
        return $this->is_symmetric
            ? self::DIRECTION_FROM
            : $this->directionFor($field);
    }

    public function isHeadless(): bool
    {
        return $this->from_field_id === null && $this->to_field_id === null;
    }
}
