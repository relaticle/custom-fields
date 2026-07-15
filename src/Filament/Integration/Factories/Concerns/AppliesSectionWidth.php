<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories\Concerns;

use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomFieldSection;

trait AppliesSectionWidth
{
    private function applyWidth(Section|Fieldset $component, CustomFieldSection $section): void
    {
        if (
            FeatureManager::isEnabled(CustomFieldsFeature::UI_SECTION_WIDTH_CONTROL)
            && $section->width instanceof CustomFieldWidth
            && $section->width !== CustomFieldWidth::_100
        ) {
            $component->columnSpan($section->width->getSpanValue());

            return;
        }

        $component->columnSpanFull();
    }
}
