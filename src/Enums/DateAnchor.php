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
            self::Today => __('custom-fields::custom-fields.enums.date_anchor.today'),
            self::FixedDate => __('custom-fields::custom-fields.enums.date_anchor.fixed_date'),
            self::CustomField => __('custom-fields::custom-fields.enums.date_anchor.custom_field'),
            self::RecordCreated => __('custom-fields::custom-fields.enums.date_anchor.record_created'),
        };
    }

    public function needsRuntimeContext(): bool
    {
        return match ($this) {
            self::CustomField, self::RecordCreated => true,
            default => false,
        };
    }
}
