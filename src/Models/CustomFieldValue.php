<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Database\Factories\CustomFieldValueFactory;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Models\Scopes\TenantScope;
use Relaticle\CustomFields\Support\SafeValueConverter;

/**
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property int $custom_field_id
 * @property ?string $string_value
 * @property ?string $text_value
 * @property ?int $integer_value
 * @property ?float $float_value
 * @property ?Collection<int, mixed> $json_value
 * @property ?bool $boolean_value
 * @property ?Carbon $date_value
 * @property ?Carbon $datetime_value
 * @property CustomField $customField
 * @property Model $entity
 */
#[ScopedBy([TenantScope::class])]
class CustomFieldValue extends Model
{
    /** @use HasFactory<CustomFieldValueFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        if ($this->table === null) {
            $this->setTable(
                config('custom-fields.database.table_names.custom_field_values')
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
            'string_value' => 'string',
            'text_value' => 'string',
            'integer_value' => 'integer',
            'float_value' => 'float',
            'json_value' => 'collection',
            'boolean_value' => 'boolean',
            'date_value' => 'date',
            'datetime_value' => 'datetime',
        ];
    }

    public static function getValueColumn(string $fieldType): string
    {
        $fieldType = CustomFieldsType::getFieldType($fieldType);
        $dataType = $fieldType->dataType;

        return match ($dataType) {
            FieldDataType::STRING, FieldDataType::FILE => 'string_value',
            FieldDataType::TEXT => 'text_value',
            FieldDataType::NUMERIC, FieldDataType::SINGLE_CHOICE => 'integer_value',
            FieldDataType::FLOAT => 'float_value',
            FieldDataType::DATE => 'date_value',
            FieldDataType::DATE_TIME => 'datetime_value',
            FieldDataType::BOOLEAN => 'boolean_value',
            FieldDataType::MULTI_CHOICE => 'json_value',
        };
    }

    /**
     * @return BelongsTo<CustomField, self>
     */
    public function customField(): BelongsTo
    {
        /** @var BelongsTo<CustomField, self> */
        return $this->belongsTo(CustomFields::customFieldModel());
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function getValue(): mixed
    {
        $column = static::getValueColumn($this->customField->type);

        return $this->$column;
    }

    public function setValue(mixed $value): void
    {
        $column = static::getValueColumn($this->customField->type);

        // Convert the value to a database-safe format based on the field type
        $safeValue = SafeValueConverter::toDbSafe(
            $value,
            $this->customField->type
        );

        $this->$column = $safeValue;
    }
}
