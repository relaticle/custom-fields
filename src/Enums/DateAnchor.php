<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum DateAnchor: string implements HasLabel
{
    case Today = 'today';
    case FixedDate = 'fixed_date';
    case CustomField = 'custom_field';
    case RecordCreated = 'record_created';

    public function getLabel(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::FixedDate => 'Fixed Date',
            self::CustomField => 'Another Field',
            self::RecordCreated => 'Record Creation Date',
        };
    }
}
