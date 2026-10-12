<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class RelationshipDefinitionData extends Data
{
    /**
     * Create a new instance of the RelationshipDefinitionData class.
     *
     * @param  string  $code  The machine-readable semantic of the relationship.
     * @param  FieldSlotData|null  $fromField  The presentation slot on the from entity.
     * @param  FieldSlotData|null  $toField  The presentation slot on the to entity.
     */
    public function __construct(
        public string $code,
        public string $fromEntityType,
        public string $toEntityType,
        public RelationshipCardinality $cardinality,
        public bool $isSymmetric = false,
        public ?FieldSlotData $fromField = null,
        public ?FieldSlotData $toField = null,
    ) {}
}
