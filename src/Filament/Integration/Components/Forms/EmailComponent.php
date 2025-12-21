<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\MultiValueInput\MultiValueInputComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class EmailComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): MultiValueInputComponent
    {
        $allowMultiple = $customField->settings->allow_multiple ?? false;
        $maxValues = $allowMultiple
            ? ($customField->settings->max_values ?? 10)
            : 1;

        return MultiValueInputComponent::make($customField->getFieldName())
            ->email()
            ->allowMultiple($allowMultiple)
            ->maxValues($maxValues)
            ->addLabel(__('custom-fields::custom-fields.email.add_email_placeholder'))
            ->placeholder(__('custom-fields::custom-fields.email.add_email_placeholder'))
            ->nestedRecursiveRules(['email', 'max:254'])
            ->rules(['array', 'max:'.$maxValues]);
    }
}
