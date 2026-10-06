<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Relaticle\CustomFields\FieldTypeSystem\Definitions\RecordFieldType;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class FieldSlotData extends Data
{
    /**
     * Create a new instance of the FieldSlotData class.
     *
     * @param  string  $name  The display name of the record field rendering this end.
     * @param  int|string|null  $sectionId  The section the field belongs to.
     * @param  int|string|null  $fieldId  An existing field to adopt as this slot, for a caller
     *                                    that wrote the field itself. Name and section then
     *                                    describe the row that is already there.
     * @param  string  $type  The field type rendering this end. A slot the caller leaves alone
     *                        is the one-way record field, which is what every migrated
     *                        definition holds.
     */
    public function __construct(
        public string $name,
        public int|string|null $sectionId = null,
        public int|string|null $fieldId = null,
        public string $type = RecordFieldType::KEY,
    ) {}
}
