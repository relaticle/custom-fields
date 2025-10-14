<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\ValueResolver;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Exceptions\MissingRecordTitleAttributeException;
use Relaticle\CustomFields\FieldTypeSystem\FieldManager;
use Relaticle\CustomFields\Models\CustomField;
use Throwable;

final readonly class LookupResolver
{
    /**
     * Resolve lookup values based on the custom field configuration.
     *
     * @param  array<int, mixed>  $values
     * @return Collection<int, mixed>
     *
     * @throws Throwable
     */
    public function resolveLookupValues(array $values, CustomField $customField): Collection
    {
        // Check if the field type accepts arbitrary values (like tags-input)
        $fieldTypeManager = app(FieldManager::class);
        $fieldTypeInstance = $fieldTypeManager->getFieldTypeInstance($customField->type);

        if ($fieldTypeInstance && $fieldTypeInstance->getData()->acceptsArbitraryValues) {
            return collect($values);
        }

        if ($customField->lookup_type === null) {
            return $customField->options->whereIn('id', $values)->pluck('name');
        }

        [$lookupInstance, $recordTitleAttribute] = $this->getLookupAttributes($customField->lookup_type);

        return $lookupInstance->whereIn('id', $values)->pluck($recordTitleAttribute);
    }

    /**
     * @return array{0: mixed, 1: string}
     *
     * @throws Throwable
     */
    protected function getLookupAttributes(string $lookupType): array
    {
        $lookupModelPath = Relation::getMorphedModel($lookupType) ?? $lookupType;
        $lookupInstance = app($lookupModelPath);

        $resourcePath = Filament::getModelResource($lookupModelPath);
        $resourceInstance = app($resourcePath);
        $recordTitleAttribute = $resourceInstance->getRecordTitleAttribute();

        throw_if(
            $recordTitleAttribute === null,
            new MissingRecordTitleAttributeException(sprintf('The `%s` does not have a record title custom attribute.', $resourcePath))
        );

        return [$lookupInstance, $recordTitleAttribute];
    }
}
