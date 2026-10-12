<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\RecordSelectInput\RecordSelectInputComponent;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresRecordSelects;
use Relaticle\CustomFields\Models\CustomField;

final readonly class RecordSelectComponent extends AbstractFormComponent
{
    use ConfiguresRecordSelects;

    public function create(CustomField $customField): RecordSelectInputComponent
    {
        return $this->configureRecordSelect(
            RecordSelectInputComponent::make($customField->getFieldName()),
            $customField,
        );
    }
}
