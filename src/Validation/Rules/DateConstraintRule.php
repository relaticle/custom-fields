<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Data\DateConstraintValue;

final readonly class DateConstraintRule implements ValidationRule
{
    /**
     * @param  array<string, mixed>|null  $formData
     */
    public function __construct(
        private DateConstraintValue $constraint,
        private string $comparison,
        private ?array $formData = null,
        private ?Model $record = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $boundary = $this->constraint->resolve(
            getCallback: fn (string $path): mixed => data_get($this->formData, $path),
            record: $this->record,
        );

        $date = Carbon::parse($value)->startOfDay();
        $boundaryDate = $boundary->startOfDay();

        $passes = match ($this->comparison) {
            'after_or_equal' => $date->greaterThanOrEqualTo($boundaryDate),
            'before_or_equal' => $date->lessThanOrEqualTo($boundaryDate),
            default => true,
        };

        if (! $passes) {
            $fail($this->getMessage($boundaryDate));
        }
    }

    private function getMessage(Carbon $boundary): string
    {
        $formattedDate = $boundary->format(CustomFields::dateDisplayFormat() ?? 'M j, Y');

        return match ($this->comparison) {
            'after_or_equal' => sprintf('The :attribute must be on or after %s.', $formattedDate),
            'before_or_equal' => sprintf('The :attribute must be on or before %s.', $formattedDate),
            default => 'The :attribute has an invalid date.',
        };
    }
}
