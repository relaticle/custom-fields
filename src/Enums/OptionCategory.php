<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum OptionCategory: string implements HasLabel
{
    case Unstarted = 'unstarted';
    case Started = 'started';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unstarted => __('custom-fields::custom-fields.enums.option_category.unstarted'),
            self::Started => __('custom-fields::custom-fields.enums.option_category.started'),
            self::Completed => __('custom-fields::custom-fields.enums.option_category.completed'),
            self::Cancelled => __('custom-fields::custom-fields.enums.option_category.cancelled'),
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }
}
