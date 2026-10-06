<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Visibility;

use Illuminate\Support\Collection;

final readonly class JsValueFormatter
{
    /**
     * Format JavaScript value using the same logic as FieldConfigurator.
     */
    public function format(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            $value === 'true' => 'true',
            $value === 'false' => 'false',
            is_string($value) => $this->toJsString($value),
            is_int($value) => (string) $value,
            is_float($value) => number_format($value, 10, '.', ''),
            is_array($value) => collect($value)
                ->map(fn (mixed $item): string => $this->format($item))
                ->pipe(
                    fn (Collection $collection): string => '['.
                        $collection->implode(', ').
                        ']'
                ),
            default => $this->toJsString((string) $value),
        };
    }

    /**
     * Emit a single-quoted JS string literal. Single quotes (never double) keep the expression safe
     * inside Filament's double-quoted `x-bind:class="…"` attribute, and control characters are
     * stripped so the generated visibleJs never spans multiple lines or breaks Alpine parsing.
     */
    private function toJsString(string $value): string
    {
        $escaped = str_replace(
            ['\\', "'", "\r", "\n", "\t"],
            ['\\\\', "\\'", '', ' ', ' '],
            $value,
        );

        return sprintf("'%s'", $escaped);
    }
}
