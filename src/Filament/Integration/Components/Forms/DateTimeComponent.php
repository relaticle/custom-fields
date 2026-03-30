<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class DateTimeComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): DateTimePicker
    {
        $configuredFormat = CustomFields::dateTimeDisplayFormat();

        return DateTimePicker::make($customField->getFieldName())
            ->native(false)
            ->format('Y-m-d H:i:s')
            ->displayFormat($configuredFormat ?? 'Y-m-d H:i:s')
            ->placeholder($configuredFormat ? Carbon::now()->format($configuredFormat) : 'YYYY-MM-DD HH:MM:SS');
    }
}
