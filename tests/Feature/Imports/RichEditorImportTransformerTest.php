<?php

declare(strict_types=1);

use Relaticle\CustomFields\Facades\CustomFieldsType;

beforeEach(function (): void {
    $fieldType = CustomFieldsType::getFieldTypeInstance('rich-editor');
    $this->transformer = $fieldType->configure()->getImportTransformer();
});

it('throws instead of returning false when the array state cannot be encoded as JSON', function (): void {
    expect(fn () => ($this->transformer)(["\xB1\x31"]))->toThrow(RuntimeException::class);
});

it('encodes a valid array state as JSON', function (): void {
    $result = ($this->transformer)(['value' => 'ok']);

    expect($result)->toBe('{"value":"ok"}');
});

it('wraps plain text lines in paragraph tags', function (): void {
    $result = ($this->transformer)("line one\nline two");

    expect($result)->toBe('<p>line one</p><p>line two</p>');
});
