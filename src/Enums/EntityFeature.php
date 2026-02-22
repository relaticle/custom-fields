<?php

// ABOUTME: Enum defining available features for entity configurations
// ABOUTME: Replaces string constants with type-safe enum values

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum EntityFeature: string implements HasLabel
{
    case CUSTOM_FIELDS = 'custom_fields';
    case LOOKUP_SOURCE = 'lookup_source';
    case SCOPED_MANAGEMENT = 'scoped_management';

    public function getLabel(): string
    {
        return match ($this) {
            self::CUSTOM_FIELDS => 'Custom Fields',
            self::LOOKUP_SOURCE => 'Lookup Source',
            self::SCOPED_MANAGEMENT => 'Scoped Management',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::CUSTOM_FIELDS => 'Entity can have custom fields attached',
            self::LOOKUP_SOURCE => 'Entity can be used as a lookup source for choice fields',
            self::SCOPED_MANAGEMENT => 'Entity custom fields are managed per-parent record, not on the global management page',
        };
    }
}
