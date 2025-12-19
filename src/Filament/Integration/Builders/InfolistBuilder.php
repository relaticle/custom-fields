<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Infolists\Components\Entry;
use Filament\Schemas\Components\Component;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    private bool $withoutSections = false;

    public function build(): Component
    {
        return InfolistContainer::make()
            ->forModel($this->explicitModel ?? null)
            ->hiddenLabels($this->hiddenLabels)
            ->visibleWhenFilled($this->visibleWhenFilled)
            ->withoutSections($this->withoutSections)
            ->only($this->only)
            ->except($this->except);
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

        $getVisibleFields = fn (CustomFieldSection $section) => $backendVisibilityService
            ->getVisibleFields($this->model, $section->fields)
            ->filter(fn (CustomField $field): bool => $field->typeData->infolistEntry !== null)
            ->map($createField);

        if ($this->withoutSections) {
            return $this->getFilteredSections()
                ->flatMap($getVisibleFields)
                ->filter();
        }

        return $this->getFilteredSections()
            ->map(function (CustomFieldSection $section) use ($sectionInfolistsFactory, $getVisibleFields) {
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
