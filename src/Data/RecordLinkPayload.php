<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Illuminate\Contracts\Support\Arrayable;
use Spatie\LaravelData\Data;

/**
 * What one record field carries into the write path.
 *
 * The contract is the ordered id list every consumer already sends. The map form adds the
 * one thing a list cannot: the caller's confirmation that taking a record away from the
 * holder of a single end is meant (spec 2.3).
 */
final class RecordLinkPayload extends Data
{
    /**
     * @param  array<int, int|string>  $ids
     * @param  array<int, string>  $confirmed  the ids the caller agreed to take from a holder
     */
    public function __construct(
        public array $ids,
        public array $confirmed = [],
    ) {}

    public static function fromValue(mixed $value): self
    {
        $value = self::unwrap($value);

        if (is_array($value) && array_key_exists('ids', $value)) {
            $ids = self::ids($value['ids']);

            return new self($ids, self::confirmedIds($value, $ids));
        }

        return new self(self::ids($value));
    }

    /**
     * Which records the caller confirmed displacing. `replace` answers for the whole payload,
     * which is the documented form a host sends; a picker names the one record the user agreed
     * to move, so a record added later never inherits that answer.
     *
     * @param  array<array-key, mixed>  $value
     * @param  array<int, int|string>  $ids
     * @return array<int, string>
     */
    public static function confirmedIds(array $value, array $ids): array
    {
        $ids = array_map(strval(...), $ids);

        if (($value['replace'] ?? false) === true) {
            return $ids;
        }

        $confirmed = array_map(strval(...), self::ids($value['confirmed'] ?? []));

        return array_values(array_intersect($confirmed, $ids));
    }

    /**
     * An empty payload is a real value that closes every edge, so only null and blank ids
     * fall away here.
     *
     * @return array<int, int|string>
     */
    private static function ids(mixed $value): array
    {
        $value = self::unwrap($value);
        $ids = is_array($value) ? $value : [$value];

        return array_values(array_filter(
            $ids,
            static fn (mixed $id): bool => is_int($id) || (is_string($id) && $id !== ''),
        ));
    }

    private static function unwrap(mixed $value): mixed
    {
        return $value instanceof Arrayable ? $value->toArray() : $value;
    }
}
