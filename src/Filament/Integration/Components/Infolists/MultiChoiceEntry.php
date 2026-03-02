<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\ViewEntry;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

final class MultiChoiceEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField): ViewEntry
    {
        $options = $customField->options->pluck('name', 'id')->all();
        $optionColors = $this->resolveOptionColors($customField);

        return ViewEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->view('custom-fields::infolists.checkbox-list-entry')
            ->state(fn (HasCustomFields $record): array => [
                'options' => $options,
                'selected' => $record->getCustomFieldValue($customField) ?? [],
                'optionColors' => $optionColors,
            ]);
    }

    /** @return array<int, string> */
    private function resolveOptionColors(CustomField $customField): array
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS)
            || ! $customField->settings->enable_option_colors
            || $customField->lookup_type) {
            return [];
        }

        return $customField->options
            ->filter(fn ($option): bool => filled($option->settings->color))
            ->pluck('settings.color', 'id')
            ->all();
    }
}
