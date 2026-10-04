<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Builders\Concerns;

use Closure;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;

trait ResolvesFields
{
    /** @var array<Closure(Collection<int, CustomField>, $this): Collection<int, CustomField>> */
    private array $fieldFilters = [];

    /** @var array<Closure(Collection<int, CustomFieldSection>, $this): Collection<int, CustomFieldSection>> */
    private array $sectionFilters = [];

    /** @param Closure(Collection<int, CustomField>, $this): Collection<int, CustomField> $callback */
    public function filterFieldsUsing(Closure $callback): static
    {
        $this->fieldFilters[] = $callback;

        return $this;
    }

    /** @param Closure(Collection<int, CustomFieldSection>, $this): Collection<int, CustomFieldSection> $callback */
    public function filterSectionsUsing(Closure $callback): static
    {
        $this->sectionFilters[] = $callback;

        return $this;
    }

    /**
     * @param  Collection<int, CustomField>  $fields
     * @return Collection<int, CustomField>
     */
    protected function applyFieldFilters(Collection $fields): Collection
    {
        $filters = $this->fieldFilters;

        if ($filters === [] || $fields->isEmpty()) {
            return $fields->values();
        }

        $fields = $fields->values();

        foreach ($filters as $filter) {
            $fields = $filter($fields, $this);
        }

        return $fields->values();
    }

    /**
     * @param  Collection<int, CustomFieldSection>  $sections
     * @return Collection<int, CustomFieldSection>
     */
    protected function applySectionFilters(Collection $sections): Collection
    {
        $filters = $this->sectionFilters;

        if ($filters === [] || $sections->isEmpty()) {
            return $sections->values();
        }

        $sections = $sections->map(fn (CustomFieldSection $section): CustomFieldSection => (clone $section)
            ->setRelation('fields', clone $section->fields));

        foreach ($filters as $filter) {
            $sections = $filter($sections, $this);
        }

        return $sections->values();
    }

    /**
     * @return Collection<int, CustomFieldSection>
     */
    public function getSections(): Collection
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)) {
            return collect();
        }

        $sections = $this->applySectionFilters($this->getFilteredSections());

        $keptFields = $this->applyFieldFilters($sections->flatMap(fn (CustomFieldSection $section): Collection => $section->fields))
            ->keyBy(fn (CustomField $field): int|string => $field->getKey());

        return $sections
            ->map(function (CustomFieldSection $section) use ($keptFields): CustomFieldSection {
                $resolved = clone $section;
                $resolved->setRelation('fields', $section->fields->filter(fn (CustomField $field): bool => $keptFields->has($field->getKey()))->values());

                return $resolved;
            })
            ->filter(fn (CustomFieldSection $section): bool => $section->fields->isNotEmpty())
            ->values();
    }

    /**
     * @return Collection<int, CustomField>
     */
    public function getFields(): Collection
    {
        if ($this->getModel() === null) {
            return collect();
        }

        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)) {
            return $this->applyFieldFilters($this->getFieldsDirectly());
        }

        return $this->getSections()->flatMap(
            fn (CustomFieldSection $section): Collection => $section->fields
        );
    }
}
