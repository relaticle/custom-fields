<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Expectation;
use Relaticle\CustomFields\Tests\TestCase;

// Apply base test configuration to all tests
uses(TestCase::class, RefreshDatabase::class)->in(__DIR__);

expect()->extend('toBeSameModel', fn (Model $model) => $this
    ->is($model)->toBeTrue());

// Custom field-specific expectations
expect()->extend('toHaveCustomFieldValue', function (string $fieldCode, mixed $expectedValue): Expectation {
    $customFieldValue = $this->value->customFieldValues
        ->firstWhere('customField.code', $fieldCode);

    return expect($customFieldValue?->getValue())->toBe($expectedValue);
});

expect()->extend('toHaveValidationError', function (string $fieldCode, string $rule) {
    $this->assertHasFormErrors(['custom_fields.'.$fieldCode => $rule]);

    return $this;
});

expect()->extend('toHaveFieldType', fn (string $expectedType): Expectation => expect($this->value->type)->toBe($expectedType));

expect()->extend('toBeActive', fn (): Expectation => expect($this->value->active)->toBeTrue());

expect()->extend('toBeInactive', fn (): Expectation => expect($this->value->active)->toBeFalse());

expect()->extend('toHaveValidationRule', function (string $rule, array $parameters = []): Expectation {
    $validationRules = $this->value->validation_rules ?? [];
    $hasRule = collect($validationRules)->contains(fn ($validationRule): bool => $validationRule['name'] === $rule &&
           ($parameters === [] || $validationRule['parameters'] === $parameters));

    return expect($hasRule)->toBeTrue(sprintf("Expected field to have validation rule '%s' with parameters: ", $rule).json_encode($parameters));
});

expect()->extend('toHaveVisibilityCondition', function (string $fieldCode, string $operator, mixed $value): Expectation {
    $conditions = $this->value->settings->visibility->conditions;

    if (! $conditions) {
        return expect(false)->toBeTrue('Expected field to have visibility conditions, but none were found');
    }

    $hasCondition = $conditions->toCollection()->contains(fn ($condition): bool => $condition->field_code === $fieldCode &&
           $condition->operator->value === $operator &&
           $condition->value === $value);

    return expect($hasCondition)->toBeTrue(sprintf("Expected field to have visibility condition for '%s' %s ", $fieldCode, $operator).json_encode($value));
});

expect()->extend('toHaveCorrectComponent', function (string $expectedComponent): Expectation {
    $fieldType = $this->value->type;
    $actualComponent = match ($fieldType) {
        'text', 'number', 'currency', 'link' => 'TextInput',
        'textarea' => 'Textarea',
        'select', 'multi_select' => 'Select',
        'checkbox' => 'Checkbox',
        'checkbox-list' => 'CheckboxList',
        'radio' => 'Radio',
        'toggle' => 'Toggle',
        'date' => 'DatePicker',
        'date-time' => 'DateTimePicker',
        'rich-editor' => 'RichEditor',
        'markdown-editor' => 'MarkdownEditor',
        'tags-input' => 'TagsInput',
        'color-picker' => 'ColorPicker',
        'toggle-buttons' => 'ToggleButtons',
        default => 'Unknown'
    };

    return expect($actualComponent)->toBe($expectedComponent);
});
