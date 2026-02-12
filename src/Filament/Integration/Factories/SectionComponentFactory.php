<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Services\Visibility\FrontendVisibilityService;

final readonly class SectionComponentFactory
{
    public function __construct(
        private FrontendVisibilityService $frontendVisibilityService,
        private BackendVisibilityService $backendVisibilityService,
    ) {}

    /**
     * @param  Collection<int, CustomField>|null  $allFields
     */
    public function create(
        CustomFieldSection $customFieldSection,
        ?Collection $allFields = null,
        ?Model $record = null
    ): Section|Fieldset|Grid {
        $component = match ($customFieldSection->type) {
            CustomFieldSectionType::SECTION => Section::make($customFieldSection->name)
                ->columnSpanFull()
                ->description($customFieldSection->description)
                ->columns(12),
            CustomFieldSectionType::FIELDSET => Fieldset::make('custom_fields.'.$customFieldSection->code)
                ->columnSpanFull()
                ->label($customFieldSection->name)
                ->columns(12),
            CustomFieldSectionType::HEADLESS => Grid::make(12)->columnSpanFull(),
        };

        if ($this->shouldApplySectionVisibility($customFieldSection)) {
            $this->applySectionVisibility($component, $customFieldSection, $allFields, $record);
        }

        return $component;
    }

    private function shouldApplySectionVisibility(CustomFieldSection $customFieldSection): bool
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)) {
            return false;
        }

        $visibility = $customFieldSection->settings->visibility ?? null;

        return $visibility?->requiresConditions() ?? false;
    }

    private function applySectionVisibility(
        Section|Fieldset|Grid $component,
        CustomFieldSection $section,
        ?Collection $allFields,
        ?Model $record
    ): void {
        $jsExpression = $this->frontendVisibilityService->buildSectionVisibilityExpression($section, $allFields);

        if (filled($jsExpression)) {
            // visibleJs alone handles both initial state (via x-cloak) and reactivity.
            // Do NOT combine with visible() — server-side visible(false) prevents the
            // component from rendering entirely, which blocks visibleJs from ever executing.
            $component->visibleJs($jsExpression);

            return;
        }

        // Fallback to server-side only when no JS expression is available
        $visibility = $section->settings->visibility ?? null;

        if ($record instanceof Model && $visibility?->hasModelAttributeConditions()) {
            $component->visible(
                fn () => $this->backendVisibilityService->isSectionVisible($record, $section, $allFields ?? collect())
            );
        }
    }
}
