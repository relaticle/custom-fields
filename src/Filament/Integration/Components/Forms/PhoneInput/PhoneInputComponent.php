<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms\PhoneInput;

use Closure;
use Filament\Forms\Components\Concerns\HasNestedRecursiveValidationRules;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Contracts\HasNestedRecursiveValidationRules as HasNestedRecursiveValidationRulesContract;
use Filament\Forms\Components\Field;
use Filament\Support\Concerns\HasExtraAlpineAttributes;
use Relaticle\CustomFields\Services\Phone\CountryPhoneService;

/**
 * A custom Filament form field for phone numbers with country selector.
 *
 * Supports single or multiple phone numbers with country code selection.
 * Stores values in E.164 format (e.g., +14155551234).
 */
class PhoneInputComponent extends Field implements HasNestedRecursiveValidationRulesContract
{
    use HasExtraAlpineAttributes;
    use HasNestedRecursiveValidationRules;
    use HasPlaceholder;

    protected string $view = 'custom-fields::forms.phone-input';

    protected bool|Closure $allowMultiple = false;

    protected int|Closure $maxValues = 1;

    protected string|Closure|null $defaultCountry = null;

    protected string|Closure|null $addLabel = null;

    protected string|Closure|null $emptyStateLabel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        // Transform state for validation: convert {country, number} objects to E.164 strings
        $this->mutateStateForValidationUsing(function (mixed $state): array {
            if (! is_array($state)) {
                return [];
            }

            $service = app(CountryPhoneService::class);
            $defaultCountry = $this->getDefaultCountry();

            return array_values(array_filter(
                array_map(function (mixed $entry) use ($service, $defaultCountry): ?string {
                    if (! is_array($entry)) {
                        return is_string($entry) ? $entry : null;
                    }

                    $country = $entry['country'] ?? $defaultCountry;
                    $number = $entry['number'] ?? '';

                    if (blank($number)) {
                        return null;
                    }

                    return $service->formatToE164($country, $number);
                }, $state),
                fn (?string $value): bool => filled($value)
            ));
        });

        $this->dehydrateStateUsing(function (PhoneInputComponent $component, mixed $state): array {
            if (! is_array($state)) {
                return [];
            }

            $service = app(CountryPhoneService::class);
            $defaultCountry = $component->getDefaultCountry();

            // Convert {country, number} objects back to E.164 strings
            return array_values(array_filter(
                array_map(
                    function (mixed $entry) use ($service, $defaultCountry): ?string {
                        // Handle string E.164 values (already formatted)
                        if (is_string($entry)) {
                            return filled($entry) ? $entry : null;
                        }

                        if (! is_array($entry)) {
                            return null;
                        }

                        $country = $entry['country'] ?? $defaultCountry;
                        $number = $entry['number'] ?? '';

                        if (blank($number)) {
                            return null;
                        }

                        return $service->formatToE164($country, $number);
                    },
                    $state
                ),
                fn (?string $value): bool => filled($value)
            ));
        });
    }

    public function allowMultiple(bool|Closure $allow = true): static
    {
        $this->allowMultiple = $allow;

        return $this;
    }

    public function getAllowMultiple(): bool
    {
        return $this->evaluate($this->allowMultiple);
    }

    public function maxValues(int|Closure $max): static
    {
        $this->maxValues = $max;

        return $this;
    }

    public function getMaxValues(): int
    {
        return $this->evaluate($this->maxValues);
    }

    public function defaultCountry(string|Closure|null $country): static
    {
        $this->defaultCountry = $country;

        return $this;
    }

    public function getDefaultCountry(): string
    {
        $country = $this->evaluate($this->defaultCountry);

        if (filled($country)) {
            return $country;
        }

        return app(CountryPhoneService::class)->detectCountryFromLocale();
    }

    public function addLabel(string|Closure|null $label): static
    {
        $this->addLabel = $label;

        return $this;
    }

    public function getAddLabel(): string
    {
        return $this->evaluate($this->addLabel) ?? __('custom-fields::custom-fields.phone.add_label');
    }

    public function emptyStateLabel(string|Closure|null $label): static
    {
        $this->emptyStateLabel = $label;

        return $this;
    }

    public function getEmptyStateLabel(): string
    {
        return $this->evaluate($this->emptyStateLabel) ?? $this->getPlaceholder() ?? __('custom-fields::custom-fields.phone.empty_state_label');
    }

    /**
     * Get country options with short codes for selected display.
     *
     * @return array<string, string>
     */
    public function getCountryOptions(): array
    {
        return app(CountryPhoneService::class)->getCountryOptions();
    }

    /**
     * Get country options with full names for dropdown list.
     *
     * @return array<string, string>
     */
    public function getCountryOptionsWithNames(): array
    {
        return app(CountryPhoneService::class)->getCountryOptionsWithNames();
    }
}
