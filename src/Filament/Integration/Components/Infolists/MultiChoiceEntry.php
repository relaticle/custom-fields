<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\Entry;
use Filament\Infolists\Components\TextEntry as BaseTextEntry;
use Filament\Infolists\Components\ViewEntry;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Filament\Integration\Concerns\Shared\ConfiguresBadgeColors;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\ValueResolver\LookupMultiValueResolver;

final class MultiChoiceEntry extends AbstractInfolistEntry
{
    use ConfiguresBadgeColors;

    public function __construct(
        private readonly LookupMultiValueResolver $valueResolver,
    ) {}

    public function make(CustomField $customField): Entry
    {
        if ($customField->typeData->acceptsArbitraryValues) {
            return $this->makeTagsEntry($customField);
        }

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

    private function makeTagsEntry(CustomField $customField): BaseTextEntry
    {
        $entry = BaseTextEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->placeholder('—')
            ->state(fn (HasCustomFields $record): array => $this->valueResolver->resolve($record, $customField));

        return $this->applyBadgeColorsIfEnabled($entry, $customField);
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
