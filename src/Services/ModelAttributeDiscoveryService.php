<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Facades\Entities;

final class ModelAttributeDiscoveryService
{
    private const EXCLUDED_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'remember_token',
        'email_verified_at',
    ];

    private const EXCLUDED_TYPES = [
        'json',
        'binary',
        'blob',
        'longblob',
        'mediumblob',
    ];

    /**
     * @var array<string, Collection<int, array{name: string, label: string, data_type: FieldDataType}>>
     */
    private static array $cache = [];

    private const EXCLUDED_CASTS = [
        'array',
        'json',
        'collection',
        'encrypted:array',
        'encrypted:collection',
        'encrypted:json',
        'object',
        'hashed',
    ];

    /**
     * Get discoverable attributes for an entity type.
     *
     * @return Collection<int, array{name: string, label: string, data_type: FieldDataType}>
     */
    public function getAttributes(string $entityType): Collection
    {
        if (isset(self::$cache[$entityType])) {
            return self::$cache[$entityType];
        }

        $model = $this->resolveModel($entityType);

        if (! $model instanceof Model) {
            return self::$cache[$entityType] = collect();
        }

        $tableName = $model->getTable();

        if (! Schema::hasTable($tableName)) {
            return self::$cache[$entityType] = collect();
        }

        $columns = Schema::getColumns($tableName);
        $castExcludedColumns = $this->getCastExcludedColumns($model);

        $attributes = collect($columns)
            ->filter(fn (array $column): bool => $this->shouldIncludeColumn($column, $castExcludedColumns))
            ->map(fn (array $column): array => [
                'name' => (string) $column['name'],
                'label' => $this->generateLabel((string) $column['name']),
                'data_type' => $this->mapColumnType($column),
            ])
            ->values();

        return self::$cache[$entityType] = $attributes;
    }

    /**
     * Get attributes formatted as select options.
     *
     * @return array<string, string>
     */
    public function getAttributeOptions(string $entityType): array
    {
        return $this->getAttributes($entityType)
            ->mapWithKeys(fn (array $attr): array => [$attr['name'] => $attr['label']])
            ->toArray();
    }

    /**
     * Get the data type for a specific model attribute.
     */
    public function getAttributeDataType(string $entityType, string $attributeName): ?FieldDataType
    {
        return $this->getAttributes($entityType)
            ->firstWhere('name', $attributeName)['data_type'] ?? null;
    }

    public static function clearCache(?string $entityType = null): void
    {
        if ($entityType === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$entityType]);
        }
    }

    private function resolveModel(string $entityType): ?Model
    {
        $entity = Entities::getEntity($entityType);

        if ($entity) {
            $modelClass = $entity->getModelClass();

            return new $modelClass;
        }

        if (class_exists($entityType) && is_subclass_of($entityType, Model::class)) {
            return new $entityType;
        }

        return null;
    }

    /**
     * Get columns excluded by their Eloquent cast type (e.g. array, json, collection).
     * This catches JSON columns that SQLite reports as 'text'.
     *
     * @return array<string>
     */
    private function getCastExcludedColumns(Model $model): array
    {
        $excluded = [];

        foreach ($model->getCasts() as $column => $cast) {
            $baseCast = strtolower(strtok($cast, ':') ?: $cast);

            if (in_array($baseCast, self::EXCLUDED_CASTS, true)) {
                $excluded[] = $column;
            }
        }

        return $excluded;
    }

    /**
     * @param  array{name: string, type_name: string, type: string, nullable: bool}  $column
     * @param  array<string>  $castExcludedColumns
     */
    private function shouldIncludeColumn(array $column, array $castExcludedColumns = []): bool
    {
        if (in_array($column['name'], self::EXCLUDED_COLUMNS, true)) {
            return false;
        }

        if (in_array($column['name'], $castExcludedColumns, true)) {
            return false;
        }

        $typeName = strtolower($column['type_name']);

        foreach (self::EXCLUDED_TYPES as $excludedType) {
            if (str_contains($typeName, $excludedType)) {
                return false;
            }
        }

        return true;
    }

    private function generateLabel(string $columnName): string
    {
        return Str::of($columnName)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    /**
     * @param  array{name: string, type_name: string, type: string, nullable: bool}  $column
     */
    private function mapColumnType(array $column): FieldDataType
    {
        $typeName = strtolower($column['type_name']);
        $type = strtolower($column['type'] ?? '');

        if ($this->isBooleanColumn($typeName, $type)) {
            return FieldDataType::BOOLEAN;
        }

        return match (true) {
            in_array($typeName, ['varchar', 'char', 'string'], true) => FieldDataType::STRING,
            in_array($typeName, ['text', 'longtext', 'mediumtext', 'tinytext'], true) => FieldDataType::TEXT,
            in_array($typeName, ['integer', 'int', 'bigint', 'smallint', 'tinyint', 'mediumint'], true) => FieldDataType::NUMERIC,
            in_array($typeName, ['decimal', 'float', 'double', 'real', 'numeric'], true) => FieldDataType::FLOAT,
            $typeName === 'date' => FieldDataType::DATE,
            in_array($typeName, ['datetime', 'timestamp'], true) => FieldDataType::DATE_TIME,
            default => FieldDataType::STRING,
        };
    }

    private function isBooleanColumn(string $typeName, string $type): bool
    {
        if ($typeName === 'boolean' || $typeName === 'bool') {
            return true;
        }

        if ($typeName === 'tinyint' && str_contains($type, '(1)')) {
            return true;
        }

        return false;
    }
}
