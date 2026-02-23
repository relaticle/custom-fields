<?php

declare(strict_types=1);

use Relaticle\CustomFields\Validation\DateFieldReferenceValidator;

it('detects no cycle when there are no references', function (): void {
    expect(DateFieldReferenceValidator::hasCycle('field_a', []))->toBeFalse();
});

it('detects no cycle for a simple chain', function (): void {
    $fieldsWithReferences = ['field_b' => 'field_a'];

    expect(DateFieldReferenceValidator::hasCycle('field_b', $fieldsWithReferences))->toBeFalse();
});

it('detects a direct cycle between two fields', function (): void {
    $fieldsWithReferences = ['field_a' => 'field_b', 'field_b' => 'field_a'];

    expect(DateFieldReferenceValidator::hasCycle('field_a', $fieldsWithReferences))->toBeTrue();
});

it('detects an indirect cycle through three fields', function (): void {
    $fieldsWithReferences = ['field_a' => 'field_b', 'field_b' => 'field_c', 'field_c' => 'field_a'];

    expect(DateFieldReferenceValidator::hasCycle('field_a', $fieldsWithReferences))->toBeTrue();
});

it('detects no cycle in a longer chain', function (): void {
    $fieldsWithReferences = ['field_a' => 'field_b', 'field_b' => 'field_c'];

    expect(DateFieldReferenceValidator::hasCycle('field_a', $fieldsWithReferences))->toBeFalse();
});
