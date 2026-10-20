<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\Scopes\TenantScope;

final readonly class KeepsStoredDecimals implements ValidationRule
{
    public function __construct(private CustomField $customField) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $decimalPlaces = (int) $value;

        if ($decimalPlaces >= $this->customField->getDecimalPlaces()) {
            return;
        }

        $finerValues = $this->customField->values()
            ->withoutGlobalScope(TenantScope::class)
            ->whereNotNull('float_value')
            ->whereRaw('ROUND(CAST(float_value AS DECIMAL(30, 10)), ?) <> CAST(float_value AS DECIMAL(30, 10))', [$decimalPlaces])
            ->count();

        if ($finerValues === 0) {
            return;
        }

        $fail(trans_choice('custom-fields::custom-fields.currency.decimal_places_below_stored', $finerValues, [
            'count' => $finerValues,
        ]));
    }
}
