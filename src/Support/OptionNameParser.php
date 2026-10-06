<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

/**
 * Reads a pasted list of choice-option names, one per line.
 */
final class OptionNameParser
{
    /**
     * The cap protects the Livewire payload a large repeater round-trips, not the database.
     */
    public const int MAX_NAMES = 100;

    /**
     * Names the editor can append, in the order they were pasted. Blank lines are dropped and
     * a name is kept once, compared case-insensitively against the rest of the paste and
     * against the rows already in the editor, which is the rule the repeater validates with.
     *
     * @param  array<int|string, mixed>  $existingNames
     * @return array{names: list<string>, duplicates: int, truncated: bool}
     */
    public static function parse(?string $input, array $existingNames = []): array
    {
        $seen = [];

        foreach ($existingNames as $existingName) {
            if (is_string($existingName) && trim($existingName) !== '') {
                $seen[mb_strtolower(trim($existingName))] = true;
            }
        }

        $names = [];
        $duplicates = 0;
        $truncated = false;

        foreach (preg_split('/\R/', (string) $input) ?: [] as $line) {
            $name = trim($line);

            if ($name === '') {
                continue;
            }

            $key = mb_strtolower($name);

            if (isset($seen[$key])) {
                $duplicates++;

                continue;
            }

            if (count($names) === self::MAX_NAMES) {
                $truncated = true;

                break;
            }

            $seen[$key] = true;
            $names[] = $name;
        }

        return ['names' => $names, 'duplicates' => $duplicates, 'truncated' => $truncated];
    }
}
