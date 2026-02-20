<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class VisibilityConditionData extends Data
{
    public function __construct(
        public string $field_code,
        public VisibilityOperator $operator,
        public mixed $value,
        public ConditionSource $source = ConditionSource::CustomField,
    ) {}

    public function isModelAttribute(): bool
    {
        return $this->source === ConditionSource::ModelAttribute;
    }

    public function isCustomField(): bool
    {
        return $this->source === ConditionSource::CustomField;
    }
}
