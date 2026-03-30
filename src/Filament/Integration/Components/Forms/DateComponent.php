<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class DateComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): DatePicker
    {
        $configuredFormat = CustomFields::dateDisplayFormat();

        return DatePicker::make($customField->getFieldName())
            ->native(false)
            ->format('Y-m-d')
            ->displayFormat($configuredFormat ?? 'Y-m-d')
            ->placeholder($configuredFormat ? Carbon::now()->format($configuredFormat) : 'YYYY-MM-DD');
    }
}
