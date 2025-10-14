<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Models\CustomFieldSection;

class SectionInfolistsFactory
{
    public function create(CustomFieldSection $customFieldSection): Section|Fieldset|Grid
    {
        return match ($customFieldSection->type) {
            CustomFieldSectionType::SECTION => Section::make($customFieldSection->name)
                ->description($customFieldSection->description),

            CustomFieldSectionType::FIELDSET => Fieldset::make($customFieldSection->name),

            CustomFieldSectionType::HEADLESS => Grid::make($customFieldSection->column_span ?? 1),
        };
    }
}
