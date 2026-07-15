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
use Relaticle\CustomFields\Filament\Integration\Factories\Concerns\AppliesSectionWidth;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Services\Visibility\CoreVisibilityLogicService;
use Relaticle\CustomFields\Services\Visibility\FrontendVisibilityService;

final readonly class SectionComponentFactory
{
    use AppliesSectionWidth;

    public function __construct(
        private FrontendVisibilityService $frontendVisibilityService,
        private BackendVisibilityService $backendVisibilityService,
        private CoreVisibilityLogicService $coreLogic,
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
                ->description($customFieldSection->description)
                ->columns(12),
            CustomFieldSectionType::FIELDSET => Fieldset::make('custom_fields.'.$customFieldSection->code)
                ->label($customFieldSection->name)
                ->columns(12),
            CustomFieldSectionType::HEADLESS => Grid::make(12)->columnSpanFull(),
        };

        if (in_array($customFieldSection->type, [CustomFieldSectionType::SECTION, CustomFieldSectionType::FIELDSET], true)) {
            $this->applyWidth($component, $customFieldSection);
        }

        if ($this->shouldApplySectionVisibility($customFieldSection)) {
            $this->applySectionVisibility($component, $customFieldSection, $allFields, $record);
        }

        return $component;
    }

    private function shouldApplySectionVisibility(CustomFieldSection $section): bool
    {
        return FeatureManager::isEnabled(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY)
            && $this->coreLogic->hasSectionVisibilityConditions($section);
    }

    /**
     * @param  Collection<int, CustomField>|null  $allFields
     */
    private function applySectionVisibility(
        Section|Fieldset|Grid $component,
        CustomFieldSection $section,
        ?Collection $allFields,
        ?Model $record
    ): void {
        $jsExpression = $this->frontendVisibilityService->buildSectionVisibilityExpression(
            $section,
            $allFields
        );

        if ($jsExpression !== null) {
            // Use visibleJs only -- do NOT combine with visible()
            // Server-side visible(false) prevents the component from rendering,
            // which blocks visibleJs from ever executing
            $component->visibleJs($jsExpression);

            return;
        }

        // Fallback: server-side evaluation when JS can't be generated
        if ($record instanceof Model) {
            $component->visible(
                fn (): bool => $this->backendVisibilityService->isSectionVisible(
                    $record,
                    $section,
                    $allFields ?? collect()
                )
            );
        }
    }
}
