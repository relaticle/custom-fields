<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Filament\Support\Contracts\HasLabel;

/**
 * Declared date convention for CSV import parsing.
 *
 * `3/4/2024` is 3 April in Europe and 4 March in the US. Guessing it, as
 * `Carbon::parse` does, silently transposes day and month whenever both are 12 or under.
 * Declaring the convention gives the cell exactly one meaning.
 */
enum ImportDateFormat: string implements HasLabel
{
    case ISO = 'iso';
    case EUROPEAN = 'european';
    case AMERICAN = 'american';

    /**
     * `Y` matches a two-digit year, so without a floor `1/1/24` parses to 1 January 24 AD.
     */
    private const int MINIMUM_YEAR = 1000;

    /**
     * A written-out month is unambiguous in either word order, so both named
     * conventions accept all of these. Only `ISO` excludes them, because a column
     * declared ISO should mean the `Y-m-d` family and nothing else.
     *
     * @var list<string>
     */
    private const array TEXTUAL_FORMATS = ['j F Y', 'j M Y', 'F j, Y', 'F jS Y', 'M j, Y', 'M jS Y'];

    public function getLabel(): string
    {
        return match ($this) {
            self::ISO => 'ISO standard',
            self::EUROPEAN => 'European (day first)',
            self::AMERICAN => 'American (month first)',
        };
    }

    /**
     * @return array<int, string>
     */
    public function getExamples(bool $withTime = false): array
    {
        if ($withTime) {
            return match ($this) {
                self::ISO => ['2024-05-15 16:00:00'],
                self::EUROPEAN => ['2024-05-15 16:00:00', '15/05/2024 16:00:00'],
                self::AMERICAN => ['2024-05-15 16:00:00', '05/15/2024 16:00:00'],
            };
        }

        return match ($this) {
            self::ISO => ['2024-05-15'],
            self::EUROPEAN => ['2024-05-15', '15/05/2024', '15 May 2024'],
            self::AMERICAN => ['2024-05-15', '05/15/2024', 'May 15, 2024'],
        };
    }

    public function parse(string $value, bool $withTime = false): ?CarbonImmutable
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach ($this->getParseFormats($withTime) as $format) {
            $parsed = $this->parseStrictly($format, $value);

            if ($parsed instanceof CarbonImmutable) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * `createFromFormat` overflows silently: `d/m/Y` turns 31/02/2024 into 2 March and
     * `Y-m-d` turns 2024-02-31 into the same. The `!` prefix does not prevent it and
     * `Carbon::hasFormat()` does not detect it. `getLastErrors()` reports a warning for
     * every overflow, so that is the gate.
     */
    private function parseStrictly(string $format, string $value): ?CarbonImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!'.$format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($parsed === false) {
            return null;
        }

        if ($errors !== false && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0)) {
            return null;
        }

        if ((int) $parsed->format('Y') < self::MINIMUM_YEAR) {
            return null;
        }

        return CarbonImmutable::instance($parsed);
    }

    /**
     * ISO forms come first in every list so a `Y-m-d` cell is never re-read as something
     * else, and every list carries the textual and datetime forms the previous
     * `Carbon::parse` fallback accepted, so nothing that imports today stops importing.
     *
     * @return array<int, string>
     */
    private function getParseFormats(bool $withTime): array
    {
        $iso = $withTime
            ? ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i']
            : ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d H:i'];

        $localised = match ($this) {
            self::ISO => [],
            self::EUROPEAN => $withTime
                ? ['d/m/Y H:i:s', 'd-m-Y H:i:s', 'd.m.Y H:i:s', 'j/n/Y H:i:s', 'd/m/Y H:i', 'j/n/Y H:i']
                : ['d/m/Y', 'd-m-Y', 'd.m.Y', 'j/n/Y', 'j-n-Y', 'j.n.Y', ...self::TEXTUAL_FORMATS],
            self::AMERICAN => $withTime
                ? ['m/d/Y H:i:s', 'm-d-Y H:i:s', 'n/j/Y H:i:s', 'm/d/Y H:i', 'n/j/Y H:i']
                : ['m/d/Y', 'm-d-Y', 'n/j/Y', 'n-j-Y', ...self::TEXTUAL_FORMATS],
        };

        return [...$iso, ...$localised];
    }
}
