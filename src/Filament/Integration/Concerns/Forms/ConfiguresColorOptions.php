<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Forms;

use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Trait for configuring color options in form components.
 *
 * Standardizes color option handling across components that support
 * colored options (Radio, CheckboxList, ToggleButtons, etc.).
 *
 * Different components require different approaches:
 * - Radio/CheckboxList: Use descriptions with color indicators
 * - ToggleButtons: Use native colors() method
 * - Select: Use HTML with colored spans
 */
trait ConfiguresColorOptions
{
    /**
     * Check if color options are enabled for a field.
     */
    protected function hasColorOptionsEnabled(CustomField $customField): bool
    {
        return FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS)
            && $customField->settings->enable_option_colors;
    }

    /**
     * Get options filtered to only those with colors.
     *
     * @return array<int|string, string> Options that have colors
     */
    protected function getColoredOptions(CustomField $customField): array
    {
        return $customField->options
            ->filter(fn (mixed $option): bool => filled($option->settings->color ?? null))
            ->mapWithKeys(fn (mixed $option): array => [$option->id => $option->name])
            ->all();
    }

    /**
     * Get color mapping for ToggleButtons-style components.
     *
     * @return array<int|string, string> Option ID => color mappings
     */
    protected function getColorMapping(CustomField $customField): array
    {
        return $customField->options
            ->filter(fn (mixed $option): bool => filled($option->settings->color ?? null))
            ->mapWithKeys(fn (mixed $option): array => [$option->id => $option->settings->color])
            ->all();
    }

    /**
     * Generate color indicator descriptions for Radio/CheckboxList style components.
     *
     * @param  array<int|string>  $optionIds
     * @return array<int|string, string> Option ID => description mappings
     */
    protected function getColorDescriptions(array $optionIds, CustomField $customField): array
    {
        return array_map(
            fn (int|string $optionId): string => $this->getColoredOptionDescription((string) $optionId, $customField),
            $optionIds
        );
    }

    /**
     * Generate HTML for colored option indicator.
     *
     * Creates a small colored square indicator for an option.
     */
    protected function getColoredOptionDescription(string $optionId, CustomField $customField): string
    {
        $option = $customField->options->firstWhere('id', $optionId);
        if (! $option || ! $option->settings->color) {
            return '';
        }

        return sprintf("<span style='display: inline-block; width: 12px; height: 12px; background-color: %s; border-radius: 2px; margin-right: 4px;'></span>", $option->settings->color);
    }

    /**
     * Get enhanced options with HTML color indicators for Select-style components.
     *
     * @return array<int|string, string> Option ID => HTML label mappings
     */
    protected function getSelectColoredOptions(CustomField $customField): array
    {
        return $customField->options
            ->mapWithKeys(function (mixed $option): array {
                $color = $option->settings->color;
                $text = $option->name;

                if ($color) {
                    return [
                        $option->id => str(
                            '<span style="display: flex; align-items: center; gap: 0.3rem;">
                            <span style=" width: 0.7rem;
  height: 0.7rem;
  border-radius: 50%;
  display: inline-block;
  background-color:{BACKGROUND_COLOR}"></span>
                            <span>{LABEL}</span>
                            </span>'
                        )
                            ->replace(['{BACKGROUND_COLOR}', '{LABEL}'], [e($color), e($text)])
                            ->toString(),
                    ];
                }

                return [$option->id => $text];
            })
            ->all();
    }
}
