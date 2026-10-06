<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\ForeignIdColumnDefinition;
use InvalidArgumentException;

/**
 * Key columns for the tables added in 4.0, shaped by config('custom-fields.database.key_type').
 * The tables that predate it keep the publish-and-hand-edit path a ULID host uses today.
 */
final class KeyType
{
    public const string BIGINT = 'bigint';

    public const string ULID = 'ulid';

    public const string UUID = 'uuid';

    public static function primary(Blueprint $table): ColumnDefinition
    {
        return match (self::current()) {
            self::BIGINT => $table->id(),
            self::ULID => $table->ulid('id')->primary(),
            self::UUID => $table->uuid('id')->primary(),
        };
    }

    public static function foreign(Blueprint $table, string $column): ForeignIdColumnDefinition
    {
        return match (self::current()) {
            self::BIGINT => $table->foreignId($column),
            self::ULID => $table->foreignUlid($column),
            self::UUID => $table->foreignUuid($column),
        };
    }

    /**
     * Morph ends address host records, so the bigint default defers to the host's own
     * Schema::defaultMorphKeyType() exactly as the pre-4.0 migrations do.
     */
    public static function morphs(Blueprint $table, string $name, bool $nullable = false): void
    {
        $method = match (self::current()) {
            self::BIGINT => $nullable ? 'nullableMorphs' : 'morphs',
            self::ULID => $nullable ? 'nullableUlidMorphs' : 'ulidMorphs',
            self::UUID => $nullable ? 'nullableUuidMorphs' : 'uuidMorphs',
        };

        $table->{$method}($name);
    }

    /**
     * @return self::BIGINT|self::ULID|self::UUID
     */
    private static function current(): string
    {
        $keyType = (string) config('custom-fields.database.key_type', self::BIGINT);

        if (! in_array($keyType, [self::BIGINT, self::ULID, self::UUID], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported custom-fields database key type [%s].', $keyType));
        }

        return $keyType;
    }
}
