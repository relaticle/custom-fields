<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Facades\Entities;
use Throwable;

/**
 * Discovers and indexes database columns from Eloquent models for use
 * as model attribute conditions in visibility rules.
 *
 * Static cache persists across requests in Octane. Reset via clearCache()
 * in service provider's terminating() callback.
 */
final class ModelAttributeDiscoveryService
{
    private const array EXCLUDED_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'password',
        'remember_token',
        'email_verified_at',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    private const array EXCLUDED_CAST_TYPES = [
        'array',
        'json',
        'collection',
        'object',
        'hashed',
    ];

    /** @var array<string, Collection<string, array{code: string, label: string, data_type: FieldDataType}>> */
    private static array $cache = [];

    /**
     * Get all discoverable attributes for an entity type.
     *
     * @return Collection<string, array{code: string, label: string, data_type: FieldDataType}>
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

        $excludedByCast = $this->getCastExcludedColumns($model);

        $columns = rescue(
            fn () => Schema::getColumns($model->getTable()),
            []
        );

        $attributes = collect($columns)
            ->filter(fn (array $column): bool => $this->shouldIncludeColumn($column, $excludedByCast))
            ->mapWithKeys(fn (array $column): array => [
                (string) $column['name'] => [
                    'code' => (string) $column['name'],
                    'label' => $this->generateLabel((string) $column['name']),
                    'data_type' => $this->mapColumnType($column, $model),
                ],
            ]);

        return self::$cache[$entityType] = $attributes;
    }

    /**
     * Get attribute options formatted for select dropdowns.
     *
     * @return array<string, string>
     */
    public function getAttributeOptions(string $entityType): array
    {
        return $this->getAttributes($entityType)
            ->mapWithKeys(fn (array $attr): array => [$attr['code'] => $attr['label']])
            ->toArray();
    }

    /**
     * Get the data type for a specific attribute.
     */
    public function getAttributeDataType(string $entityType, string $attributeCode): ?FieldDataType
    {
        $attributes = $this->getAttributes($entityType);
        $attribute = $attributes->get($attributeCode);

        return $attribute['data_type'] ?? null;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    private function resolveModel(string $entityType): ?Model
    {
        $entity = Entities::getEntity($entityType);

        if ($entity) {
            $modelClass = $entity->getModelClass();

            return $this->instantiateModel($modelClass);
        }

        if (class_exists($entityType) && is_subclass_of($entityType, Model::class)) {
            return $this->instantiateModel($entityType);
        }

        return null;
    }

    private function instantiateModel(string $class): ?Model
    {
        try {
            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                return null;
            }

            /** @var Model $model */
            $model = $reflection->newInstanceWithoutConstructor();

            return $model;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Get column names that should be excluded based on their cast type.
     *
     * @return array<int, string>
     */
    private function getCastExcludedColumns(Model $model): array
    {
        $excluded = [];

        try {
            foreach ($model->getCasts() as $column => $cast) {
                $baseCast = strtok((string) $cast, ':');

                if (in_array($baseCast, self::EXCLUDED_CAST_TYPES, true)) {
                    $excluded[] = $column;
                }
            }
        } catch (Throwable) {
            // Model may not be fully bootable without constructor
        }

        return $excluded;
    }

    /**
     * @param  array{name: string, type_name: string, type: string, nullable: bool}  $column
     * @param  array<int, string>  $castExcluded
     */
    private function shouldIncludeColumn(array $column, array $castExcluded): bool
    {
        if (in_array($column['name'], self::EXCLUDED_COLUMNS, true)) {
            return false;
        }

        if (in_array($column['name'], $castExcluded, true)) {
            return false;
        }

        $excludedTypes = ['json', 'binary', 'blob', 'longblob', 'mediumblob'];

        if (in_array(strtolower($column['type_name']), $excludedTypes, true)) {
            return false;
        }

        return true;
    }

    /**
     * @param  array{name: string, type_name: string, type: string, nullable: bool}  $column
     */
    private function mapColumnType(array $column, Model $model): FieldDataType
    {
        if ($this->isBooleanColumn($column, $model)) {
            return FieldDataType::BOOLEAN;
        }

        $typeName = strtolower($column['type_name']);

        return match (true) {
            in_array($typeName, ['varchar', 'char', 'enum'], true) => FieldDataType::STRING,
            in_array($typeName, ['text', 'mediumtext', 'longtext'], true) => FieldDataType::TEXT,
            in_array($typeName, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint'], true) => FieldDataType::NUMERIC,
            in_array($typeName, ['float', 'double', 'decimal', 'real', 'numeric'], true) => FieldDataType::FLOAT,
            $typeName === 'date' => FieldDataType::DATE,
            in_array($typeName, ['datetime', 'timestamp'], true) => FieldDataType::DATE_TIME,
            default => FieldDataType::STRING,
        };
    }

    /**
     * @param  array{name: string, type_name: string, type: string, nullable: bool}  $column
     */
    private function isBooleanColumn(array $column, Model $model): bool
    {
        $casts = [];

        try {
            $casts = $model->getCasts();
        } catch (Throwable) {
            // Fall through to type-based detection
        }

        if (isset($casts[$column['name']])) {
            $cast = strtok((string) $casts[$column['name']], ':');

            return in_array($cast, ['bool', 'boolean'], true);
        }

        $type = strtolower($column['type']);

        return str_starts_with($type, 'tinyint(1)');
    }

    private function generateLabel(string $columnName): string
    {
        return Str::of($columnName)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }
}
