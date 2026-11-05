<?php

declare(strict_types=1);

// ABOUTME: Manages temporary storage of custom field data during imports using WeakMap
// ABOUTME: Provides automatic memory cleanup and prevents SQL errors during import

namespace Relaticle\CustomFields\Filament\Integration\Support\Imports;

use Illuminate\Database\Eloquent\Model;
use WeakMap;

/**
 * Stores custom field data during import process using WeakMap for automatic memory management.
 *
 * This class solves the problem of Filament trying to set custom_fields_* as model attributes,
 * which causes SQL errors. WeakMap ensures automatic cleanup when models are garbage collected.
 *
 * @internal
 */
final class ImportDataStorage
{
    /**
     * WeakMap storage for custom field data during import.
     * Automatically cleans up when model instances are garbage collected.
     *
     * @var ?WeakMap<Model, array<string, mixed>>
     */
    private static ?WeakMap $storage = null;

    /**
     * The WeakMap storage, created on first use.
     *
     * @return WeakMap<Model, array<string, mixed>>
     */
    private static function storage(): WeakMap
    {
        return self::$storage ??= new WeakMap;
    }

    /**
     * Store custom field data for a record.
     *
     * @param  Model  $record  The record being imported
     * @param  string  $fieldCode  The custom field code
     * @param  mixed  $value  The value to store
     */
    public static function set(Model $record, string $fieldCode, mixed $value): void
    {
        $storage = self::storage();

        $data = $storage[$record] ?? [];
        $data[$fieldCode] = $value;
        $storage[$record] = $data;
    }

    /**
     * Store multiple custom field values at once.
     *
     * @param  Model  $record  The record being imported
     * @param  array<string, mixed>  $values  The values to store
     */
    public static function setMultiple(Model $record, array $values): void
    {
        $storage = self::storage();

        $data = $storage[$record] ?? [];
        $storage[$record] = array_merge($data, $values);
    }

    /**
     * Get all custom field data for a record.
     *
     * @param  Model  $record  The record being imported
     * @return array<string, mixed> The custom field data
     */
    public static function get(Model $record): array
    {
        return self::storage()[$record] ?? [];
    }

    /**
     * Get and clear custom field data for a record.
     *
     * @param  Model  $record  The record being imported
     * @return array<string, mixed> The custom field data
     */
    public static function pull(Model $record): array
    {
        $storage = self::storage();

        $data = $storage[$record] ?? [];
        unset($storage[$record]);

        return $data;
    }

    /**
     * Check if a record has stored data.
     *
     * @param  Model  $record  The record to check
     */
    public static function has(Model $record): bool
    {
        return isset(self::storage()[$record]);
    }

    /**
     * Clear all stored data.
     * Use with caution - mainly for testing.
     */
    public static function clearAll(): void
    {
        self::$storage = new WeakMap;
    }
}
