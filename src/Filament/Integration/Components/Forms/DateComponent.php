<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\DatePicker;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class DateComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): DatePicker
    {
        return DatePicker::make($customField->getFieldName())
            ->native(false)
            ->format('Y-m-d')
            ->displayFormat('Y-m-d')
            ->placeholder('YYYY-MM-DD');
    }
}
