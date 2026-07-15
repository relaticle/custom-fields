<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Filament\Integration\Factories\Concerns\AppliesSectionWidth;
use Relaticle\CustomFields\Models\CustomFieldSection;

final class SectionInfolistsFactory
{
    use AppliesSectionWidth;

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
}
