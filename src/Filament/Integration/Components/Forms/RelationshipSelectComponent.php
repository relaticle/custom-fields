<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\RelationshipPicker\RelationshipPickerComponent;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\ConfiguresRecordSelects;
use Relaticle\CustomFields\Models\CustomField;

final readonly class RelationshipSelectComponent extends AbstractFormComponent
{
    use ConfiguresRecordSelects;

    public function create(CustomField $customField): RelationshipPickerComponent
    {
        return $this->configureRecordSelect(
            RelationshipPickerComponent::make($customField->getFieldName()),
            $customField,
        );
    }
}
