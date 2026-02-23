<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\DateAnchor;
use Relaticle\CustomFields\Enums\DateOffsetDirection;
use Relaticle\CustomFields\Enums\DateUnit;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class DateConstraintValue extends Data
{
    public function __construct(
        public DateAnchor $anchor,
        public int $offset = 0,
        public DateUnit $offsetUnit = DateUnit::Days,
        public DateOffsetDirection $offsetDirection = DateOffsetDirection::After,
        public ?string $fixedDate = null,
        public ?string $fieldReference = null,
    ) {}

    public function resolve(?Closure $getCallback = null, ?Model $record = null): Carbon
    {
        $base = match ($this->anchor) {
            DateAnchor::Today => now()->startOfDay(),
            DateAnchor::FixedDate => Carbon::parse($this->fixedDate)->startOfDay(),
            DateAnchor::CustomField => $this->resolveCustomField($getCallback),
            DateAnchor::RecordCreated => $record?->getAttribute('created_at')?->copy() ?? now()->startOfDay(),
        };

        if ($this->offset === 0) {
            return $base;
        }

        return $this->offsetDirection === DateOffsetDirection::Before
            ? $base->sub($this->offsetUnit->value, $this->offset)
            : $base->add($this->offsetUnit->value, $this->offset);
    }

    private function resolveCustomField(?Closure $getCallback): Carbon
    {
        $value = $getCallback ? $getCallback("custom_fields.{$this->fieldReference}") : null;

        if ($value === null || $value === '') {
            return now()->startOfDay();
        }

        return Carbon::parse($value)->startOfDay();
    }
}
