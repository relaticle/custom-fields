<?php

declare(strict_types=1);

use Relaticle\CustomFields\Support\DatabaseFieldConstraints;

it('includes mariadb in the supported database drivers', function () {
    $reflection = new ReflectionClass(DatabaseFieldConstraints::class);
    $property = $reflection->getProperty('constraints');
    $constraints = $property->getValue();

    expect($constraints)->toHaveKey('mariadb');
});

it('has the same constraint keys for mariadb as mysql', function () {
    $reflection = new ReflectionClass(DatabaseFieldConstraints::class);
    $property = $reflection->getProperty('constraints');
    $constraints = $property->getValue();

    expect(array_keys($constraints['mariadb']))->toBe(array_keys($constraints['mysql']));
});
