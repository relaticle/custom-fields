<?php

declare(strict_types=1);

use Relaticle\CustomFields\Support\DatabaseFieldConstraints;

it('includes mariadb in text_value driver-specific constraints', function (): void {
    $constraints = DatabaseFieldConstraints::getConstraintsForColumn('text_value');

    expect($constraints['max'])->toHaveKey('mariadb');
});

it('has the same max value for mariadb as mysql', function (): void {
    $constraints = DatabaseFieldConstraints::getConstraintsForColumn('text_value');

    expect($constraints['max']['mariadb'])->toBe($constraints['max']['mysql']);
});
