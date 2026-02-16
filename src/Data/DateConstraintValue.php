<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Carbon\Carbon;
use Relaticle\CustomFields\Enums\DateConstraintMode;
use Relaticle\CustomFields\Enums\DateUnit;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class DateConstraintValue extends Data
{
    public function __construct(
        public DateConstraintMode $mode,
        public ?string $absoluteValue = null,
        public ?int $relativeValue = null,
        public ?DateUnit $relativeUnit = null,
    ) {}

    public function resolve(): Carbon
    {
        return match ($this->mode) {
            DateConstraintMode::Absolute => Carbon::parse($this->absoluteValue),
            DateConstraintMode::Relative => now()->add(
                $this->relativeUnit->value,
                $this->relativeValue ?? 0,
            ),
        };
    }
}
