<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum AvatarShape: string implements HasLabel
{
    case Circle = 'circle';
    case Square = 'square';

    public function getLabel(): string
    {
        return match ($this) {
            self::Circle => __('custom-fields::custom-fields.enums.avatar_shape.circle'),
            self::Square => __('custom-fields::custom-fields.enums.avatar_shape.square'),
        };
    }

    public function getCssClass(): string
    {
        return match ($this) {
            self::Circle => 'rounded-full',
            self::Square => 'rounded-md',
        };
    }
}
