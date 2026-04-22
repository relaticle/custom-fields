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
            self::CUSTOM_FIELDS => __('custom-fields::custom-fields.enums.entity_feature.labels.custom_fields'),
            self::LOOKUP_SOURCE => __('custom-fields::custom-fields.enums.entity_feature.labels.lookup_source'),
            self::SCOPED_MANAGEMENT => __('custom-fields::custom-fields.enums.entity_feature.labels.scoped_management'),
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::CUSTOM_FIELDS => __('custom-fields::custom-fields.enums.entity_feature.descriptions.custom_fields'),
            self::LOOKUP_SOURCE => __('custom-fields::custom-fields.enums.entity_feature.descriptions.lookup_source'),
            self::SCOPED_MANAGEMENT => __('custom-fields::custom-fields.enums.entity_feature.descriptions.scoped_management'),
        };
    }

    public function description(): string
    {
        return $this->getDescription();
    }
}
