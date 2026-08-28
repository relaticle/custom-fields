<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Relaticle\CustomFields\Imports\UnresolvedValue;

final class RejectsUnresolvedValue implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UnresolvedValue) {
            return;
        }

        $fail($value->reason);
    }
}
