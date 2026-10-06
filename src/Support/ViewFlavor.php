<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use InvalidArgumentException;
use Relaticle\CustomFields\Enums\UiFlavor;
use Relaticle\CustomFields\Enums\UiSurface;

final class ViewFlavor
{
    public static function flavor(UiSurface $surface): UiFlavor
    {
        // Both keys resolve before the lookup: an override must not hide a typo in the other.
        $configured = self::configured();
        $overrides = self::overrides();

        return $overrides[$surface->value] ?? $configured;
    }

    // Every surface reads the same two keys, so validating once at boot turns a typo into a
    // failure on the first request rather than on the first render of a forked surface.
    public static function validate(): void
    {
        try {
            self::configured();
            self::overrides();
        } catch (InvalidArgumentException $invalidArgumentException) {
            // A cached bad flavor has to stay recoverable: throwing here would take
            // config:clear down with the config it exists to clear.
            if (! app()->runningInConsole()) {
                throw $invalidArgumentException;
            }

            report($invalidArgumentException);
        }
    }

    // Null is the native flavor: the caller renders the view it shipped with, so a flavor
    // decides what a surface looks like and never what it does.
    public static function view(UiSurface $surface): ?string
    {
        return match (self::flavor($surface)) {
            UiFlavor::Polished => 'custom-fields::flavors.polished.'.$surface->value,
            UiFlavor::Native => null,
        };
    }

    private static function configured(): UiFlavor
    {
        return self::parse(config('custom-fields.ui.flavor', UiFlavor::Polished->value), 'custom-fields.ui.flavor');
    }

    /**
     * @return array<string, UiFlavor>
     */
    private static function overrides(): array
    {
        $configured = config('custom-fields.ui.flavor_overrides', []);

        if (! is_array($configured)) {
            throw new InvalidArgumentException('The custom-fields.ui.flavor_overrides config must be an array of surface keys to flavors.');
        }

        $overrides = [];

        foreach ($configured as $key => $flavor) {
            $surface = UiSurface::tryFrom(self::stringify($key));

            if (! $surface instanceof UiSurface) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown custom-fields UI surface [%s] in custom-fields.ui.flavor_overrides. Forked surfaces are: %s.',
                    self::stringify($key),
                    implode(', ', array_column(UiSurface::cases(), 'value')),
                ));
            }

            $overrides[$surface->value] = self::parse($flavor, 'custom-fields.ui.flavor_overrides.'.$surface->value);
        }

        return $overrides;
    }

    private static function parse(mixed $flavor, string $configKey): UiFlavor
    {
        $parsed = UiFlavor::tryFrom(self::stringify($flavor));

        if (! $parsed instanceof UiFlavor) {
            throw new InvalidArgumentException(sprintf(
                'Unknown custom-fields UI flavor [%s] in %s. Available flavors are: %s.',
                self::stringify($flavor),
                $configKey,
                implode(', ', array_column(UiFlavor::cases(), 'value')),
            ));
        }

        return $parsed;
    }

    private static function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }
}
