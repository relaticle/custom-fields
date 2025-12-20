<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms\MultiValueInput;

use Closure;
use Filament\Forms\Components\Concerns\HasNestedRecursiveValidationRules;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Contracts\HasNestedRecursiveValidationRules as HasNestedRecursiveValidationRulesContract;
use Filament\Forms\Components\Field;
use Filament\Support\Concerns\HasExtraAlpineAttributes;

/**
 * A custom Filament form field for managing single or multiple values
 * with a popover-based editing interface (Attio/Twenty UX pattern).
 *
 * Single value: Shows a pill that opens popover on click
 * Multiple values: Shows pills + add button, popover with editable rows
 */
class MultiValueInputComponent extends Field implements HasNestedRecursiveValidationRulesContract
{
    use HasExtraAlpineAttributes;
    use HasNestedRecursiveValidationRules;
    use HasPlaceholder;

    protected string $view = 'custom-fields::forms.multi-value-input';

    protected string|Closure $inputType = 'text';

    protected bool|Closure $allowMultiple = false;

    protected int|Closure $maxValues = 1;

    protected string|Closure|null $addLabel = null;

    protected string|Closure|null $emptyStateLabel = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->default([]);

        $this->afterStateHydrated(static function (MultiValueInputComponent $component, mixed $state): void {
            if (is_array($state)) {
                return;
            }

            if ($state === null) {
                $component->state([]);

                return;
            }

            // Convert single value to array
            $component->state([$state]);
        });

        $this->dehydrateStateUsing(static function (MultiValueInputComponent $component, mixed $state): array {
            if (! is_array($state)) {
                return $state !== null ? [$state] : [];
            }

            // Filter out empty values
            return array_values(array_filter($state, fn (mixed $value): bool => filled($value)));
        });
    }

    public function inputType(string|Closure $type): static
    {
        $this->inputType = $type;

        return $this;
    }

    public function getInputType(): string
    {
        return $this->evaluate($this->inputType);
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

    public function addLabel(string|Closure|null $label): static
    {
        $this->addLabel = $label;

        return $this;
    }

    public function getAddLabel(): string
    {
        return $this->evaluate($this->addLabel) ?? __('Add');
    }

    public function emptyStateLabel(string|Closure|null $label): static
    {
        $this->emptyStateLabel = $label;

        return $this;
    }

    public function getEmptyStateLabel(): string
    {
        return $this->evaluate($this->emptyStateLabel) ?? $this->getPlaceholder() ?? __('Click to add');
    }

    /**
     * Configure for email input type
     */
    public function email(): static
    {
        return $this->inputType('email');
    }

    /**
     * Configure for URL input type
     */
    public function url(): static
    {
        return $this->inputType('url');
    }
}
