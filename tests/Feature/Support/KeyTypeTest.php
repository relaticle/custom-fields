<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Relaticle\CustomFields\Support\KeyType;

$probe = function (string $keyType): array {
    config()->set('custom-fields.database.key_type', $keyType);

    $columns = [];

    DB::pretend(function () use (&$columns): void {
        Schema::create('key_type_probe', function (Blueprint $table) use (&$columns): void {
            KeyType::primary($table);
            KeyType::foreign($table, 'owner_id');
            KeyType::morphs($table, 'thing');
            KeyType::morphs($table, 'actor', nullable: true);

            foreach ($table->getColumns() as $column) {
                $columns[(string) $column->get('name')] = $column;
            }
        });
    });

    return $columns;
};

it('shapes key columns from the configured key type', function (string $keyType, string $idType) use ($probe): void {
    $columns = $probe($keyType);

    expect($columns['id']->get('type'))->toBe($idType)
        ->and($columns['owner_id']->get('type'))->toBe($idType)
        ->and($columns['thing_id']->get('type'))->toBe($idType)
        ->and($columns['actor_id']->get('type'))->toBe($idType)
        ->and($columns['thing_type']->get('type'))->toBe('string');
})->with([
    'bigint' => ['bigint', 'bigInteger'],
    'ulid' => ['ulid', 'char'],
    'uuid' => ['uuid', 'uuid'],
]);

it('makes only the nullable morph nullable', function () use ($probe): void {
    $columns = $probe('ulid');

    expect($columns['thing_id']->get('nullable'))->toBeFalsy()
        ->and($columns['actor_id']->get('nullable'))->toBeTrue();
});

it('rejects an unsupported key type', function () use ($probe): void {
    $probe('snowflake');
})->throws(InvalidArgumentException::class);
