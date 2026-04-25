<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Simple visibility modes for conditional fields.
 */
enum VisibilityMode: string implements HasLabel
{
    case ALWAYS_VISIBLE = 'always_visible';
    case SHOW_WHEN = 'show_when';
    case HIDE_WHEN = 'hide_when';

    public function getLabel(): string
    {
        return match ($this) {
            self::ALWAYS_VISIBLE => __('custom-fields::custom-fields.enums.visibility_mode.always_visible'),
            self::SHOW_WHEN => __('custom-fields::custom-fields.enums.visibility_mode.show_when'),
            self::HIDE_WHEN => __('custom-fields::custom-fields.enums.visibility_mode.hide_when'),
        };
    }

    public function requiresConditions(): bool
    {
        return $this !== self::ALWAYS_VISIBLE;
    }

    public function shouldShow(bool $conditionsMet): bool
    {
        return match ($this) {
            self::ALWAYS_VISIBLE => true,
            self::SHOW_WHEN => $conditionsMet,
            self::HIDE_WHEN => ! $conditionsMet,
        };
    }
}
