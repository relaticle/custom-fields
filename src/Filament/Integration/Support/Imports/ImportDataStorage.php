<?php

declare(strict_types=1);

// ABOUTME: Manages temporary storage of custom field data during imports
// ABOUTME: Prevents SQL errors by storing data separately from model attributes

namespace Relaticle\CustomFields\Filament\Integration\Support\Imports;

use Illuminate\Database\Eloquent\Model;

/**
 * Stores custom field data during import process.
 *
 * This class solves the problem of Filament trying to set
 * custom_fields_* as model attributes, which causes SQL errors.
 */
final class ImportDataStorage
{
    /**
     * Storage for custom field data during import.
     * Keyed by object ID to handle concurrent imports.
     *
     * @var array<int, array<string, mixed>>
     */
    private static array $storage = [];

    /**
     * Store custom field data for a record.
     *
     * @param  Model  $record  The record being imported
     * @param  string  $fieldCode  The custom field code
     * @param  mixed  $value  The value to store
     */
    public static function set(Model $record, string $fieldCode, mixed $value): void
    {
        $key = spl_object_id($record);

        if (! isset(self::$storage[$key])) {
            self::$storage[$key] = [];
        }

        self::$storage[$key][$fieldCode] = $value;
    }

    /**
     * Get all custom field data for a record.
     *
     * @param  Model  $record  The record being imported
     * @return array<string, mixed> The custom field data
     */
    public static function get(Model $record): array
    {
        $key = spl_object_id($record);

        return self::$storage[$key] ?? [];
    }

    /**
     * Get and clear custom field data for a record.
     *
     * @param  Model  $record  The record being imported
     * @return array<string, mixed> The custom field data
     */
    public static function pull(Model $record): array
    {
        $key = spl_object_id($record);
        $data = self::$storage[$key] ?? [];

        unset(self::$storage[$key]);

        return $data;
    }

    /**
     * Clear data for a specific record.
     *
     * @param  Model  $record  The record to clear data for
     */
    public static function clear(Model $record): void
    {
        unset(self::$storage[spl_object_id($record)]);
    }

    /**
     * Clear all stored data.
     * Use with caution - mainly for testing.
     */
    public static function clearAll(): void
    {
        self::$storage = [];
    }

    /**
     * Check if a record has stored data.
     *
     * @param  Model  $record  The record to check
     */
    public static function has(Model $record): bool
    {
        return isset(self::$storage[spl_object_id($record)]);
    }
}
