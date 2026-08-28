<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Imports;

use Stringable;

/**
 * An import cell the cast could not honestly convert.
 *
 * Casts run inside Filament's `Importer::castData()` loop, which aborts the row on the
 * first throw and cannot aggregate errors. Returning this instead lets the single
 * `validateData()` pass report every unresolvable column in the row at once, alongside
 * the ordinary column errors.
 */
final readonly class UnresolvedValue implements Stringable
{
    public function __construct(
        public mixed $raw,
        public string $reason,
    ) {}

    public static function make(mixed $raw, string $reason): self
    {
        return new self($raw, $reason);
    }

    public function __toString(): string
    {
        return is_scalar($this->raw) ? (string) $this->raw : '';
    }
}
