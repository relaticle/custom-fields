<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\QueryBuilders\CustomFieldQueryBuilder;

/**
 * Interface for models that have custom fields.
 *
 * @phpstan-require-extends Model
 */
interface HasCustomFields
{
    /**
     * @return CustomFieldQueryBuilder<CustomField>
     */
    public function customFields(): CustomFieldQueryBuilder;

    /**
     * @return MorphMany<CustomFieldValue, Model>
     */
    public function customFieldValues(): MorphMany;

    public function getCustomFieldValue(CustomField $customField): mixed;

    public function saveCustomFieldValue(CustomField $customField, mixed $value, ?Model $tenant = null): void;

    /**
     * @param  array<string, mixed>  $customFields
     */
    public function saveCustomFields(array $customFields, ?Model $tenant = null): void;
}
