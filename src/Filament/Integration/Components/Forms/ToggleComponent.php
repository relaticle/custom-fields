<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\Toggle;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class ToggleComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): Toggle
    {
        return Toggle::make($customField->getFieldName())
            ->onColor('success')
            ->offColor('danger')
            ->inline(false);
    }
}
