<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Carbon\Carbon;
use Relaticle\CustomFields\Enums\DateDirection;
use Relaticle\CustomFields\Enums\DateUnit;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class DateConstraintValue extends Data
{
    public function __construct(
        public int $relativeValue,
        public DateUnit $relativeUnit,
        public DateDirection $direction = DateDirection::FromNow,
    ) {}

    public function resolve(): Carbon
    {
        $value = $this->direction === DateDirection::Ago
            ? -$this->relativeValue
            : $this->relativeValue;

        return now()->add($this->relativeUnit->value, $value);
    }
}
