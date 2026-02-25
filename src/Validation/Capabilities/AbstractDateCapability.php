<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation\Capabilities;

use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Contracts\ValidationCapability;
use Relaticle\CustomFields\Data\DateConstraintValue;
use Relaticle\CustomFields\Filament\Management\Forms\Components\DateConstraintField;
use Relaticle\CustomFields\Validation\Rules\DateConstraintRule;

abstract readonly class AbstractDateCapability implements ValidationCapability
{
    abstract protected function context(): string;

    abstract protected function comparison(): string;

    abstract protected function applyConstraint(Field $component, DateConstraintValue $constraint): void;

    /** @return array<int, Component> */
    public function formSchema(string $statePath): array
    {
        return DateConstraintField::make(
            sprintf('%s.%s', $statePath, $this->key()),
            $this->label(),
            $this->context(),
        );
    }

    public function applyToComponent(Field $component, mixed $value): void
    {
        if (! $this->isValidConstraint($value)) {
            return;
        }

        $this->applyConstraint($component, DateConstraintValue::from($value));
    }

    /** @return array<int, mixed> */
    public function toRules(mixed $value): array
    {
        if (! $this->isValidConstraint($value)) {
            return [];
        }

        $constraint = DateConstraintValue::from($value);

        if ($constraint->anchor->needsRuntimeContext()) {
            return [];
        }

        return [new DateConstraintRule($constraint, $this->comparison())];
    }

    protected static function resolveDate(DateConstraintValue $constraint): Closure
    {
        return fn (Get $get, ?Model $record = null): Carbon => $constraint->resolve(
            getCallback: fn (string $path): mixed => $get($path),
            record: $record,
        );
    }

    private function isValidConstraint(mixed $value): bool
    {
        return is_array($value) && ! empty($value['anchor']);
    }
}
