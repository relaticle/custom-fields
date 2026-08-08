<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Integration\Factories\FieldInfolistsFactory;
use Relaticle\CustomFields\Filament\Integration\Factories\SectionInfolistsFactory;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;

final class InfolistBuilder extends BaseBuilder
{
    private bool $hiddenLabels = false;

    private bool $visibleWhenFilled = false;

    private ?bool $withoutSections = null;

    public function build(): InfolistContainer
    {
        $container = InfolistContainer::make()
            ->forModel($this->explicitModel ?? null)
            ->hiddenLabels($this->hiddenLabels)
            ->visibleWhenFilled($this->visibleWhenFilled)
            ->only($this->only)
            ->except($this->except)
            ->onlySections($this->onlySections);

        // Only set withoutSections if explicitly configured
        if ($this->withoutSections !== null) {
            $container->withoutSections($this->withoutSections);
        }

        return $container;
    }

    /**
     * @return Collection<int, mixed>
     */
    public function values(null|(Model&HasCustomFields) $model = null): Collection
    {
        if ($model !== null) {
            $this->forModel($model);
        }

        $fieldInfolistsFactory = app(FieldInfolistsFactory::class);
        $sectionInfolistsFactory = app(SectionInfolistsFactory::class);
        $backendVisibilityService = app(BackendVisibilityService::class);

        $createField = fn (CustomField $customField) => $fieldInfolistsFactory->create($customField)
            ->hiddenLabel($this->hiddenLabels)
            ->when($this->visibleWhenFilled, fn (Entry $field): Entry => $field->visible(fn (mixed $state): bool => filled($state)));

        // Check if sections are disabled
        $sectionsDisabled = ! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS);

        // When sections disabled, get fields directly without section context
        if ($this->withoutSections || $sectionsDisabled) {
            return $backendVisibilityService
                ->getVisibleFields($this->model, $this->getAllFields())
                ->filter(fn (CustomField $field): bool => $field->typeData->infolistEntry !== null)
                ->map($createField)
                ->filter();
        }

        $getVisibleFields = fn (CustomFieldSection $section) => $backendVisibilityService
            ->getVisibleFields($this->model, $section->fields)
            ->filter(fn (CustomField $field): bool => $field->typeData->infolistEntry !== null)
            ->map($createField);

        // Section-level conditional visibility is evaluated server-side per record, mirroring
        // SectionComponentFactory on the form. Without this the infolist would render a section
        // whenever it has any visible field, ignoring the section's own visibility condition.
        $sectionConditionalVisibilityEnabled = FeatureManager::isEnabled(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY);
        $allFields = $this->getAllFields();

        return $this->getFilteredSections()
            ->map(function (CustomFieldSection $section) use ($sectionInfolistsFactory, $getVisibleFields, $backendVisibilityService, $sectionConditionalVisibilityEnabled, $allFields) {
                if (
                    $sectionConditionalVisibilityEnabled
                    && ! $backendVisibilityService->isSectionVisible($this->model, $section, $allFields)
                ) {
                    return null;
                }

                $fields = $getVisibleFields($section);

                return $fields->isEmpty()
                    ? null
                    : $sectionInfolistsFactory->create($section)->schema($fields->toArray());
            })
            ->filter();
    }

    public function hiddenLabels(bool $hiddenLabels = true): static
    {
        $this->hiddenLabels = $hiddenLabels;

        return $this;
    }

    public function visibleWhenFilled(bool $visibleWhenFilled = true): static
    {
        $this->visibleWhenFilled = $visibleWhenFilled;

        return $this;
    }

    public function withoutSections(bool $withoutSections = true): static
    {
        $this->withoutSections = $withoutSections;

        return $this;
    }
}
