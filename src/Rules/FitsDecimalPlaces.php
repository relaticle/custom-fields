<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\Scopes\TenantScope;

final readonly class FitsDecimalPlaces implements ValidationRule
{
    public function __construct(
        private CustomField $customField,
        private string|int|null $entityId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $decimalPlaces = $this->customField->getDecimalPlaces();
        $rule = $decimalPlaces === 0 ? 'integer' : "decimal:0,{$decimalPlaces}";

        if (Validator::make(['value' => $value], ['value' => $rule])->passes()) {
            return;
        }

        if ($this->isStoredAmount($value)) {
            return;
        }

        $decimalPlaces === 0
            ? $fail('validation.integer')->translate()
            : $fail('validation.decimal')->translate(['decimal' => "0-{$decimalPlaces}"]);
    }

    private function isStoredAmount(mixed $value): bool
    {
        if ($this->entityId === null || ! is_numeric($value)) {
            return false;
        }

        $stored = $this->customField->values()
            ->withoutGlobalScope(TenantScope::class)
            ->where('entity_id', $this->entityId)
            ->value('float_value');

        return $stored !== null && (float) $stored === (float) $value;
    }
}
