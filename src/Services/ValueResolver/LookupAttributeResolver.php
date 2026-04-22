<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\ValueResolver;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Relaticle\CustomFields\Exceptions\MissingRecordTitleAttributeException;
use RuntimeException;

/**
 * Resolves the (lookup model instance, title attribute) pair for a given
 * lookup_type, by consulting Filament's registered resource for the model.
 *
 * Centralized here so LookupResolver and LookupPreloader share exactly one
 * source of truth — previously each class had its own copy of this logic.
 */
final readonly class LookupAttributeResolver
{
    /**
     * @return array{0: Model, 1: string}
     */
    public function resolve(string $lookupType): array
    {
        $lookupModelPath = Relation::getMorphedModel($lookupType) ?? $lookupType;
        $lookupInstance = app($lookupModelPath);

        $resourcePath = Filament::getModelResource($lookupModelPath);

        if ($resourcePath === null) {
            throw new RuntimeException(sprintf(
                'No Filament resource is registered for lookup model `%s`.',
                $lookupModelPath,
            ));
        }

        $resourceInstance = app($resourcePath);
        $recordTitleAttribute = $resourceInstance->getRecordTitleAttribute();

        throw_if(
            $recordTitleAttribute === null,
            new MissingRecordTitleAttributeException(sprintf(
                'The lookup model `%s` does not have a configured record title attribute on resource `%s`.',
                $lookupModelPath,
                $resourcePath,
            ))
        );

        return [$lookupInstance, $recordTitleAttribute];
    }
}
