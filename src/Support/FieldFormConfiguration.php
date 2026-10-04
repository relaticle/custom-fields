<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use Filament\Support\Enums\Width;
use InvalidArgumentException;
use Relaticle\CustomFields\Enums\FieldFormPresentation;

/**
 * How much of the field form a host puts in front of its users.
 *
 * A feature flag says what the package can do; this says what the form offers. A
 * setting left out keeps working: the value already stored survives an edit, and a
 * new field takes the default its host expects.
 */
final class FieldFormConfiguration
{
    /**
     * Every setting the form can offer. `max_values` has no entry of its own: it is
     * the ceiling on `allow_multiple` and only ever renders beside it.
     *
     * @var list<string>
     */
    public const array SETTINGS = [
        'visible_in_list',
        'visible_in_view',
        'list_toggleable_hidden',
        'searchable',
        'encrypted',
        'enable_option_colors',
        'allow_multiple',
        'unique_per_entity_type',
    ];

    public static function presentation(): FieldFormPresentation
    {
        $configured = config('custom-fields.field_form.presentation', FieldFormPresentation::SlideOver->value);

        $parsed = FieldFormPresentation::tryFrom(self::stringify($configured));

        if (! $parsed instanceof FieldFormPresentation) {
            throw new InvalidArgumentException(sprintf(
                'Unknown custom-fields field form presentation [%s] in custom-fields.field_form.presentation. Available presentations are: %s.',
                self::stringify($configured),
                implode(', ', array_column(FieldFormPresentation::cases(), 'value')),
            ));
        }

        return $parsed;
    }

    public static function isSlideOver(): bool
    {
        return self::presentation()->isSlideOver();
    }

    // A panel can run the full height of the screen; a dialog the same width is a wall.
    public static function width(): Width
    {
        return self::isSlideOver() ? Width::ScreenLarge : Width::FourExtraLarge;
    }

    public static function offers(string $setting): bool
    {
        $offered = self::offered();

        return $offered === null || in_array($setting, $offered, true);
    }

    // The two keys resolve once at boot so a typo fails on the first request rather than
    // on the first render of the field form.
    public static function validate(): void
    {
        try {
            self::presentation();
            self::offered();
        } catch (InvalidArgumentException $invalidArgumentException) {
            // A cached bad value has to stay recoverable: throwing here would take
            // config:clear down with the config it exists to clear.
            if (! app()->runningInConsole()) {
                throw $invalidArgumentException;
            }

            report($invalidArgumentException);
        }
    }

    /**
     * @return list<string>|null
     */
    private static function offered(): ?array
    {
        $configured = config('custom-fields.field_form.settings');

        if ($configured === null) {
            return null;
        }

        if (! is_array($configured)) {
            throw new InvalidArgumentException('The custom-fields.field_form.settings config must be null or a list of setting keys.');
        }

        $offered = [];

        foreach ($configured as $setting) {
            $key = self::stringify($setting);

            if (! in_array($key, self::SETTINGS, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Unknown custom-fields field form setting [%s] in custom-fields.field_form.settings. Available settings are: %s.',
                    $key,
                    implode(', ', self::SETTINGS),
                ));
            }

            $offered[] = $key;
        }

        return $offered;
    }

    private static function stringify(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : get_debug_type($value);
    }
}
