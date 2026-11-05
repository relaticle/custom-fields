<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Relaticle\CustomFields\Enums\OptionCategory;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\StatusFieldType;

/**
 * Takes the whole option list after the write: name strings, arrays with `category` or
 * `settings.category`, or option models, so every write surface can pass what it holds.
 */
final readonly class RequiresCompletedOption implements ValidationRule
{
    public function __construct(
        private string $fieldType,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->fieldType !== StatusFieldType::KEY) {
            return;
        }

        foreach (is_iterable($value) ? $value : [] as $option) {
            if ($this->categoryOf($option) === OptionCategory::Completed) {
                return;
            }
        }

        $fail(__('custom-fields::custom-fields.validation.status_requires_completed_option', [
            'category' => OptionCategory::Completed->getLabel(),
        ]));
    }

    private function categoryOf(mixed $option): ?OptionCategory
    {
        if (is_string($option)) {
            return null;
        }

        $category = data_get($option, 'settings.category') ?? data_get($option, 'category');

        if ($category instanceof OptionCategory) {
            return $category;
        }

        return is_string($category) ? OptionCategory::tryFrom($category) : null;
    }
}
