<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

final class SettingsMerger
{
    /**
     * @param  array<string, mixed>  $stored
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    public static function merge(array $stored, array $submitted): array
    {
        foreach ($submitted as $key => $value) {
            $storedValue = $stored[$key] ?? null;

            if (self::isAssociative($value) && self::isAssociative($storedValue)) {
                $stored[$key] = self::merge($storedValue, $value);

                continue;
            }

            $stored[$key] = $value;
        }

        return $stored;
    }

    private static function isAssociative(mixed $value): bool
    {
        return is_array($value) && $value !== [] && ! array_is_list($value);
    }
}
