<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Relaticle\CustomFields\FieldTypeSystem\FieldManager;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\MultiValueInput\MultiValueInputComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class LinkComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): MultiValueInputComponent
    {
        $allowMultiple = $customField->settings->allow_multiple ?? false;
        $maxValues = $allowMultiple
            ? ($customField->settings->max_values ?? 10)
            : 1;

        $fieldType = app(FieldManager::class)->getFieldTypeInstance($customField->type);

        return MultiValueInputComponent::make($customField->getFieldName())
            ->url()
            ->allowMultiple($allowMultiple)
            ->maxValues($maxValues)
            ->addLabel(__('custom-fields::custom-fields.link.add_link_placeholder'))
            ->placeholder(__('custom-fields::custom-fields.link.add_link_placeholder'))
            ->nestedRecursiveRules(['max:2048', 'regex:/^(https?:\/\/)?([a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}(\/.*)?$/'])
            ->rules(['array', 'max:'.$maxValues])
            ->dehydrateStateUsing(function (mixed $state) use ($fieldType): array {
                return collect($state)
                    ->map(function (mixed $v) use ($fieldType): string {
                        $trimmed = trim((string) $v);

                        return $fieldType ? $fieldType->setValue($trimmed) : $trimmed;
                    })
                    ->filter(fn (mixed $v): bool => filled($v))
                    ->values()
                    ->all();
            });
    }
}
