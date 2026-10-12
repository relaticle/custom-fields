<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Shared;

use Filament\Infolists\Components\TextEntry;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;

trait ConfiguresBadgeColors
{
    /**
     * @template TComponent of TextEntry|TextColumn
     *
     * @param  TComponent  $component
     * @return TComponent
     */
    protected function applyBadgeColorsIfEnabled(TextEntry|TextColumn $component, CustomField $customField): TextEntry|TextColumn
    {
        if ($customField->typeData->acceptsArbitraryValues) {
            return $this->applyTagsBadgeColors($component, $customField);
        }

        if (! $this->shouldApplyBadgeColors($customField)) {
            return $component;
        }

        return $component->badge()
            ->color(function (mixed $state) use ($customField): array {
                $color = $customField->options->where('name', $state)->first()?->settings->color;

                return Color::hex($color ?? '#000000');
            });
    }

    /**
     * Apply badge styling for tags (fields with arbitrary values).
     * Always displays as badges with predefined option colors or gray fallback.
     *
     * @template TComponent of TextEntry|TextColumn
     *
     * @param  TComponent  $component
     * @return TComponent
     */
    private function applyTagsBadgeColors(TextEntry|TextColumn $component, CustomField $customField): TextEntry|TextColumn
    {
        return $component->badge()
            ->color(function (mixed $state) use ($customField): array|string {
                if ($this->shouldApplyBadgeColors($customField)) {
                    $option = $customField->options->where('name', $state)->first();

                    if ($option?->settings->color) {
                        return Color::hex($option->settings->color);
                    }
                }

                return 'gray';
            });
    }

    private function shouldApplyBadgeColors(CustomField $customField): bool
    {
        return FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS)
            && $customField->settings->enable_option_colors
            && ! $customField->typeData->requiresRelationship;
    }
}
