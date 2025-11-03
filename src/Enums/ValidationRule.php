<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Carbon\Carbon;
use Closure;
use Exception;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Numeric;
use InvalidArgumentException;

enum ValidationRule: string implements HasLabel
{
    case ACCEPTED = 'accepted';
    case ACCEPTED_IF = 'accepted_if';
    case ACTIVE_URL = 'active_url';
    case AFTER = 'after';
    case AFTER_OR_EQUAL = 'after_or_equal';
    case ALPHA = 'alpha';
    case ALPHA_DASH = 'alpha_dash';
    case ALPHA_NUM = 'alpha_num';
    case ARRAY = 'array';
    case ASCII = 'ascii';
    case BEFORE = 'before';
    case BEFORE_OR_EQUAL = 'before_or_equal';
    case BETWEEN = 'between';
    case BOOLEAN = 'boolean';
    case CONFIRMED = 'confirmed';
    case CURRENT_PASSWORD = 'current_password';
    case DATE = 'date';
    case DATE_EQUALS = 'date_equals';
    case DATE_FORMAT = 'date_format';
    case DECIMAL = 'decimal';
    case DECLINED = 'declined';
    case DECLINED_IF = 'declined_if';
    case DIFFERENT = 'different';
    case DIGITS = 'digits';
    case DIGITS_BETWEEN = 'digits_between';
    case DIMENSIONS = 'dimensions';
    case DISTINCT = 'distinct';
    case DOESNT_START_WITH = 'doesnt_start_with';
    case DOESNT_END_WITH = 'doesnt_end_with';
    case EMAIL = 'email';
    case ENDS_WITH = 'ends_with';
    case ENUM = 'enum';
    case EXCLUDE = 'exclude';
    case EXCLUDE_IF = 'exclude_if';
    case EXCLUDE_UNLESS = 'exclude_unless';
    case EXISTS = 'exists';
    case FILE = 'file';
    case FILLED = 'filled';
    case GT = 'gt';
    case GTE = 'gte';
    case IMAGE = 'image';
    case IN = 'in';
    case IN_ARRAY = 'in_array';
    case INTEGER = 'integer';
    case IP = 'ip';
    case IPV4 = 'ipv4';
    case IPV6 = 'ipv6';
    case JSON = 'json';
    case LT = 'lt';
    case LTE = 'lte';
    case MAC_ADDRESS = 'mac_address';
    case MAX = 'max';
    case MAX_DIGITS = 'max_digits';
    case MIMES = 'mimes';
    case MIMETYPES = 'mimetypes';
    case MIN = 'min';
    case MIN_DIGITS = 'min_digits';
    case MULTIPLE_OF = 'multiple_of';
    case NOT_IN = 'not_in';
    case NOT_REGEX = 'not_regex';
    case NUMERIC = 'numeric';
    case PASSWORD = 'password';
    case PRESENT = 'present';
    case PROHIBITED = 'prohibited';
    case PROHIBITED_IF = 'prohibited_if';
    case PROHIBITED_UNLESS = 'prohibited_unless';
    case PROHIBITS = 'prohibits';
    case REGEX = 'regex';
    case REQUIRED = 'required';
    case REQUIRED_IF = 'required_if';
    case REQUIRED_UNLESS = 'required_unless';
    case REQUIRED_WITH = 'required_with';
    case REQUIRED_WITH_ALL = 'required_with_all';
    case REQUIRED_WITHOUT = 'required_without';
    case REQUIRED_WITHOUT_ALL = 'required_without_all';
    case SAME = 'same';
    case SIZE = 'size';
    case STARTS_WITH = 'starts_with';
    case STRING = 'string';
    case TIMEZONE = 'timezone';
    case UNIQUE = 'unique';
    case UPPERCASE = 'uppercase';
    case URL = 'url';
    case UUID = 'uuid';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_column(self::cases(), 'name')
        );
    }

    /**
     * Get the count of allowed parameters for a given validation rule.
     */
    public function allowedParameterCount(): int
    {
        return match ($this) {
            // Rules with unlimited parameters
            self::ACCEPTED_IF, self::DECLINED_IF, self::DIFFERENT, self::ENDS_WITH,
            self::IN, self::NOT_IN, self::REQUIRED_IF, self::REQUIRED_WITH, self::REQUIRED_WITH_ALL,
            self::REQUIRED_WITHOUT, self::REQUIRED_WITHOUT_ALL, self::STARTS_WITH,
            self::EXCLUDE_IF, self::EXCLUDE_UNLESS, self::PROHIBITS => -1,

            // Rules with exactly two parameters
            self::BETWEEN, self::DECIMAL, self::REQUIRED_UNLESS, self::PROHIBITED_IF,
            self::PROHIBITED_UNLESS, self::DIGITS_BETWEEN => 2,

            // Rules with one parameter
            self::SIZE, self::MAX, self::MIN, self::DIGITS, self::DATE_EQUALS,
            self::DATE_FORMAT, self::AFTER, self::AFTER_OR_EQUAL, self::BEFORE,
            self::BEFORE_OR_EQUAL, self::EXISTS, self::UNIQUE, self::GT, self::GTE,
            self::LT, self::LTE, self::MAX_DIGITS, self::MIN_DIGITS, self::MULTIPLE_OF => 1,

            // Default case for any unspecified rules
            default => 0
        };
    }

    /**
     * Check if the validation rule has any parameters.
     */
    public function hasParameter(): bool
    {
        $allowedCount = $this->allowedParameterCount();

        return $allowedCount > 0 || $allowedCount === -1;
    }

    public function getLabel(): string
    {
        return __('custom-fields::custom-fields.validation.labels.'.$this->name);
    }

    public function getDescription(): string
    {
        return __('custom-fields::custom-fields.validation.descriptions.'.$this->name);
    }

    /**
     * Check if a rule value is considered empty.
     *
     * Utility method to eliminate repeated null/empty checks throughout the enum.
     */
    private static function isEmptyRule(mixed $rule): bool
    {
        return in_array($rule, [null, '', '0'], true);
    }

    public static function hasParameterForRule(?string $rule): bool
    {
        if (self::isEmptyRule($rule)) {
            return false;
        }

        return self::tryFrom($rule)?->hasParameter() ?? false;
    }

    public static function getAllowedParametersCountForRule(?string $rule): int
    {
        if (self::isEmptyRule($rule)) {
            return 0;
        }

        // If we get -1 as the allowed parameter count, it means that the rule allows any number of parameters.
        // Otherwise, we return the allowed parameter count.
        $allowedCount = self::tryFrom($rule)?->allowedParameterCount();

        return $allowedCount === -1 ? 30 : $allowedCount ?? 0;
    }

    public static function getDescriptionForRule(?string $rule): string
    {
        if (self::isEmptyRule($rule)) {
            return __('custom-fields::custom-fields.validation.select_rule_description');
        }

        return self::tryFrom($rule)?->getDescription() ?? __('custom-fields::custom-fields.validation.select_rule_description');
    }

    /**
     * Get the validation rules for a parameter of this validation rule.
     *
     * @param  int  $parameterIndex  The index of the parameter (0-based)
     * @return list<Closure|Numeric|string> The validation rules for the parameter
     */
    public function getParameterValidationRule(int $parameterIndex = 0): array
    {
        return match ($this) {
            // Numeric rules
            self::SIZE, self::MIN, self::MAX => ['required', Rule::numeric()->min(PHP_INT_MIN)->max(PHP_INT_MAX)],
            self::MULTIPLE_OF => ['required', 'numeric', 'gt:0'],
            self::DIGITS, self::MAX_DIGITS, self::MIN_DIGITS => ['required', 'integer', 'min:1'],

            // Between rules
            self::BETWEEN => match ($parameterIndex) {
                0, 1 => ['required', Rule::numeric()->min(PHP_INT_MIN)->max(PHP_INT_MAX)],
                default => ['required'],
            },
            self::DIGITS_BETWEEN => match ($parameterIndex) {
                0, 1 => ['required', Rule::numeric()->integer()->min(PHP_INT_MIN)->max(PHP_INT_MAX)],
                default => ['required'],
            },
            self::DECIMAL => match ($parameterIndex) {
                0 => ['nullable', 'integer', 'min:0'], // min decimal places
                1 => ['nullable', Rule::numeric()->integer()->min(PHP_INT_MIN)->max(PHP_INT_MAX)], // max decimal places
                default => ['required'],
            },

            // Date rules
            self::DATE_FORMAT, self::REQUIRED_IF, self::REQUIRED_UNLESS, self::PROHIBITED_IF, self::PROHIBITED_UNLESS, self::ACCEPTED_IF, self::DECLINED_IF, self::MIMES, self::MIMETYPES, self::GT, self::GTE, self::LT, self::LTE => ['required', 'string'],
            self::AFTER, self::AFTER_OR_EQUAL, self::BEFORE, self::BEFORE_OR_EQUAL, self::DATE_EQUALS => [
                'required',
                function (string $attribute, mixed $value, Closure $fail): void {
                    // Accept valid date string or special values like 'today', 'tomorrow', etc.
                    if (! in_array($value, ['today', 'tomorrow', 'yesterday'], true) && Carbon::hasFormat($value, 'Y-m-d') === false) {
                        $fail(__('custom-fields::custom-fields.validation.invalid_date_format'));
                    }
                },
            ],

            // List-based rules
            self::IN, self::NOT_IN, self::STARTS_WITH, self::ENDS_WITH, self::DOESNT_START_WITH, self::DOESNT_END_WITH => [
                'required', 'string', 'min:1',
            ],

            // Regex rules
            self::REGEX, self::NOT_REGEX => [
                'required', 'string',
                function (string $attribute, string $value, Closure $fail): void {
                    try {
                        preg_match('/'.$value.'/', 'test');
                    } catch (Exception) {
                        $fail(__('custom-fields::custom-fields.validation.invalid_regex_pattern'));
                    }
                },
            ],

            // Database rules
            self::EXISTS, self::UNIQUE => [
                'required', 'string',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (in_array(preg_match('/^\w+(\.\w+)?$/', $value), [0, false], true)) {
                        $fail(__('custom-fields::custom-fields.validation.invalid_table_format'));
                    }
                },
            ],

            // File rules
            // Default for all other rules
            default => ['required', 'string', 'max:255'],
        };
    }

    /**
     * Get the help text for a specific parameter of this validation rule.
     *
     * @param  int  $parameterIndex  The index of the parameter (0-based)
     * @return string The help text for the parameter
     */
    public function getParameterHelpText(int $parameterIndex = 0): string
    {
        // For rules requiring exactly 2 parameters, strictly enforce that
        if ($this->allowedParameterCount() === 2 && $parameterIndex > 1) {
            throw new InvalidArgumentException(
                __('custom-fields::custom-fields.validation.multi_parameter_missing')
            );
        }

        return match ($this) {
            self::SIZE => __('custom-fields::custom-fields.validation.parameter_help.size'),
            self::MIN => __('custom-fields::custom-fields.validation.parameter_help.min'),
            self::MAX => __('custom-fields::custom-fields.validation.parameter_help.max'),
            self::BETWEEN => match ($parameterIndex) {
                0 => __('custom-fields::custom-fields.validation.parameter_help.between.min'),
                1 => __('custom-fields::custom-fields.validation.parameter_help.between.max'),
                default => throw new InvalidArgumentException(__('custom-fields::custom-fields.validation.between_validation_error')),
            },
            self::DIGITS => __('custom-fields::custom-fields.validation.parameter_help.digits'),
            self::DIGITS_BETWEEN => match ($parameterIndex) {
                0 => __('custom-fields::custom-fields.validation.parameter_help.digits_between.min'),
                1 => __('custom-fields::custom-fields.validation.parameter_help.digits_between.max'),
                default => throw new InvalidArgumentException(__('custom-fields::custom-fields.validation.digits_between_validation_error')),
            },
            self::DECIMAL => match ($parameterIndex) {
                0 => __('custom-fields::custom-fields.validation.parameter_help.decimal.min'),
                1 => __('custom-fields::custom-fields.validation.parameter_help.decimal.max'),
                default => throw new InvalidArgumentException(__('custom-fields::custom-fields.validation.decimal_validation_error')),
            },
            self::DATE_FORMAT => __('custom-fields::custom-fields.validation.parameter_help.date_format'),
            self::AFTER => __('custom-fields::custom-fields.validation.parameter_help.after'),
            self::BEFORE => __('custom-fields::custom-fields.validation.parameter_help.before'),
            self::IN => __('custom-fields::custom-fields.validation.parameter_help.in'),
            self::MIMES => __('custom-fields::custom-fields.validation.parameter_help.mimes'),
            self::REGEX => __('custom-fields::custom-fields.validation.parameter_help.regex'),
            self::EXISTS => __('custom-fields::custom-fields.validation.parameter_help.exists'),
            default => __('custom-fields::custom-fields.validation.parameter_help.default'),
        };
    }

    /**
     * Get the validation rules for a parameter of a specific validation rule.
     *
     * @param  string|null  $rule  The validation rule
     * @param  int  $parameterIndex  The index of the parameter (0-based)
     * @return array<int, string> The validation rules for the parameter
     */
    public static function getParameterValidationRuleFor(?string $rule, int $parameterIndex = 0): array
    {
        if (self::isEmptyRule($rule)) {
            return ['required', 'string', 'max:255'];
        }

        $ruleEnum = self::tryFrom($rule);

        // Special handling for rules that require exactly 2 parameters
        if ($ruleEnum && $ruleEnum->allowedParameterCount() === 2) {
            // Ensure we don't allow more than 2 parameters
            if ($parameterIndex > 1) {
                throw new InvalidArgumentException(__('custom-fields::custom-fields.validation.multi_parameter_missing'));
            }

            return $ruleEnum->getParameterValidationRule($parameterIndex);
        }

        return $ruleEnum?->getParameterValidationRule($parameterIndex) ?? ['required', 'string', 'max:255'];
    }

    /**
     * Get the help text for a specific parameter of a validation rule.
     *
     * @param  string|null  $rule  The validation rule
     * @param  int  $parameterIndex  The index of the parameter (0-based)
     * @return string The help text for the parameter
     */
    public static function getParameterHelpTextFor(?string $rule, int $parameterIndex = 0): string
    {
        if (self::isEmptyRule($rule)) {
            return __('custom-fields::custom-fields.validation.parameter_help.default');
        }

        return self::tryFrom($rule)?->getParameterHelpText($parameterIndex) ?? __('custom-fields::custom-fields.validation.parameter_help.default');
    }

    /**
     * Normalize a parameter value based on the validation rule type.
     *
     * @param  string|null  $rule  The validation rule
     * @param  string  $value  The parameter value to normalize
     * @param  int  $parameterIndex  The index of the parameter (0-based)
     * @return string The normalized parameter value
     */
    public static function normalizeParameterValue(?string $rule, string $value, int $parameterIndex = 0): string
    {
        if (self::isEmptyRule($rule)) {
            return $value;
        }

        $enum = self::tryFrom($rule);

        if (! $enum instanceof self) {
            return $value;
        }

        // For multi-parameter rules, ensure both parameters exist
        if ($enum->allowedParameterCount() === 2 && $parameterIndex > 1) {
            throw new InvalidArgumentException(
                __('custom-fields::custom-fields.validation.multi_parameter_missing')
            );
        }

        return match ($enum) {
            // Numeric rules - ensure they're properly formatted numbers
            self::SIZE, self::MIN, self::MAX, self::DIGITS, self::MAX_DIGITS, self::MIN_DIGITS,
            self::MULTIPLE_OF, self::BETWEEN => is_numeric($value) ? (string) floatval($value) : $value,

            // Between rules need special handling
            self::DIGITS_BETWEEN, self::DECIMAL => is_numeric($value) ? (string) intval($value) : $value,

            // Decimal rule - ensure proper integer formatting
            // Date rules - ensure they're properly formatted dates if possible
            self::AFTER, self::AFTER_OR_EQUAL, self::BEFORE, self::BEFORE_OR_EQUAL, self::DATE_EQUALS => in_array($value, ['today', 'tomorrow', 'yesterday'], true) ? $value :
                    (Carbon::hasFormat($value, 'Y-m-d') ? Carbon::parse($value)->format('Y-m-d') : $value),

            // List-based rules - trim values
            self::IN, self::NOT_IN, self::STARTS_WITH, self::ENDS_WITH,
            self::DOESNT_START_WITH, self::DOESNT_END_WITH => trim($value),

            // Default - just return the value as is
            default => $value,
        };
    }

    /**
     * Get the label for a given validation rule.
     *
     * @param  string  $rule  The validation rule.
     * @param  array<string, string>  $parameters  The parameters to be passed to the validation rule.
     * @return string The label for the given validation rule.
     */
    public static function getLabelForRule(string $rule, array $parameters = []): string
    {
        if (self::isEmptyRule($rule)) {
            return '';
        }

        $enum = self::tryFrom($rule);

        if (! $enum instanceof ValidationRule) {
            return '';
        }

        $label = $enum->getLabel();
        if ($parameters !== []) {
            $values = implode(', ', array_column($parameters, 'value'));
            $label .= ' ('.$values.')';
        }

        return $label;
    }
}
