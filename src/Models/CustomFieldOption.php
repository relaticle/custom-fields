<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\CustomFieldOptionSettingsData;
use Relaticle\CustomFields\Database\Factories\CustomFieldOptionFactory;
use Relaticle\CustomFields\Models\Scopes\SortOrderScope;
use Relaticle\CustomFields\Models\Scopes\TenantScope;

/**
 * @property int $id
 * @property ?string $name
 * @property ?string $value
 * @property ?int $sort_order
 * @property CustomFieldOptionSettingsData $settings
 * @property int $custom_field_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[ScopedBy([TenantScope::class, SortOrderScope::class])]
class CustomFieldOption extends Model
{
    /** @use HasFactory<CustomFieldOptionFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function boot(): void
    {
        parent::boot();

        // Handle encryption when saving
        static::saving(function (self $option): void {
            // Only process if we have a name and custom_field_id
            if (! isset($option->attributes['name']) || ! $option->custom_field_id) {
                return;
            }

            // Get the raw name value from attributes
            $rawName = $option->attributes['name'];

            // Load the custom field if not loaded
            if (! $option->relationLoaded('customField')) {
                $option->load('customField');
            }

            // Check if encryption is enabled
            if ($option->customField && $option->customField->settings->encrypted) {
                $option->attributes['name'] = Crypt::encryptString($rawName);
            }
        });
    }

    protected $casts = [
        'settings' => CustomFieldOptionSettingsData::class.':default',
    ];

    /**
     * The attributes that should be visible in arrays.
     *
     * @var list<string>
     */
    protected $visible = [
        'id',
        'name',
        'value',
        'settings',
        'sort_order',
        'custom_field_id',
    ];

    /**
     * Handle decryption of option name based on parent field settings
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (in_array($value, [null, '', '0'], true)) {
                    return $value;
                }

                // Load parent field if not already loaded
                if (! $this->relationLoaded('customField') && $this->custom_field_id) {
                    $this->load('customField');
                }

                // If encryption is not enabled, return as-is
                if (! $this->customField?->settings?->encrypted) {
                    return $value;
                }

                // Value is not encrypted, return as-is
                return Crypt::decryptString($value);
            }
        );
    }

    /**
     * Return value if it exists, otherwise fallback to id for backward compatibility.
     * This allows pluck('name', 'id') to work with value-based options.
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn (int $value): int|string => $this->attributes['value'] ?? $value
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes = [])
    {
        if ($this->table === null) {
            $this->setTable(
                config('custom-fields.database.table_names.custom_field_options')
            );
        }

        parent::__construct($attributes);
    }

    /**
     * @return BelongsTo<CustomField, self>
     */
    public function customField(): BelongsTo
    {
        /** @var BelongsTo<CustomField, self> */
        return $this->belongsTo(CustomFields::customFieldModel());
    }
}
