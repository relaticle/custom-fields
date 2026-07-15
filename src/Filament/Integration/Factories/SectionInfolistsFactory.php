<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomFieldSection;

final class SectionInfolistsFactory
{
    public function create(CustomFieldSection $customFieldSection): Section|Fieldset|Grid
    {
        $component = match ($customFieldSection->type) {
            CustomFieldSectionType::SECTION => Section::make($customFieldSection->name)
                ->columns(12)
                ->description($customFieldSection->description),

            CustomFieldSectionType::FIELDSET => Fieldset::make($customFieldSection->name)
                ->columns(12),

            CustomFieldSectionType::HEADLESS => Grid::make(12),
        };

        if (in_array($customFieldSection->type, [CustomFieldSectionType::SECTION, CustomFieldSectionType::FIELDSET], true)) {
            $this->applyWidth($component, $customFieldSection);
        }

        return $component;
    }

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
