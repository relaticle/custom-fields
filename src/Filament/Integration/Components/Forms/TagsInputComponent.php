<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\TagsInput;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class TagsInputComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): TagsInput
    {
        $suggestions = $this->getCustomFieldOptions($customField);

        return TagsInput::make($customField->getFieldName())
            ->suggestions($suggestions);
    }
}
