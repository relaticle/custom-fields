<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * VisibilityLogic for combining multiple conditions.
 */
enum VisibilityLogic: string implements HasLabel
{
    case ALL = 'all';
    case ANY = 'any';

    public function getLabel(): string
    {
        return match ($this) {
            self::ALL => __('custom-fields::custom-fields.enums.visibility_logic.all'),
            self::ANY => __('custom-fields::custom-fields.enums.visibility_logic.any'),
        };
    }

    /**
     * @param  array<int, bool>  $results
     */
    public function evaluate(array $results): bool
    {
        if ($results === []) {
            return false;
        }

        return match ($this) {
            self::ALL => ! in_array(false, $results, true),
            self::ANY => in_array(true, $results, true),
        };
    }
}
