<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Shared;

use Filament\Support\Colors\Color;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;

trait ConfiguresBadgeColors
{
    protected function applyBadgeColorsIfEnabled($component, CustomField $customField)
    {
        if (! $this->shouldApplyBadgeColors($customField)) {
            return $component;
        }

        return $component->badge()
            ->color(function ($state) use ($customField): array {
                $color = $customField->options->where('name', $state)->first()?->settings->color;

                return Color::hex($color ?? '#000000');
            });
    }

    protected function shouldApplyBadgeColors(CustomField $customField): bool
    {
        return FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS)
            && $customField->settings->enable_option_colors
            && ! $customField->lookup_type;
    }
}
