<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\Checkbox;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class CheckboxComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): Checkbox
    {
        return Checkbox::make($customField->getFieldName())->inline(false);
    }
}
