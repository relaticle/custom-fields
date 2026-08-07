<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\QueryBuilders\CustomFieldQueryBuilder;

abstract class BaseBuilder
{
    protected Model&HasCustomFields $model;

    protected Model|string|null $explicitModel = null;

    protected ?Builder $sections = null;

    protected array $except = [];

    protected array $only = [];

    /** @var array<int, int> */
    protected array $onlySections = [];

    public function forSchema(Schema $schema): static
    {
        /** @var Model & HasCustomFields $model */
        $model = $schema->getRecord() ?? $schema->getModel();

        return $this->forModel($model);
    }

    public function forModel(Model|string $model): static
    {
        if (is_string($model)) {
            $model = app($model);
        }

        if (! $model instanceof HasCustomFields) {
            throw new InvalidArgumentException('Model must implement HasCustomFields interface.');
        }

        if (! $model instanceof Model) {
            throw new InvalidArgumentException('Model must be an Eloquent Model.');
        }

        if (! $this instanceof TableBuilder) {
            $model->load('customFieldValues.customField.options');
        }

        $this->model = $model;
        $this->explicitModel = $model;

        // Only initialize sections query when sections are enabled
        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)) {
            $this->sections = CustomFields::newSectionModel()->query()
                ->forEntityType($model::class)
                ->orderBy('sort_order');
        }

        return $this;
    }

    public function except(array $fieldCodes): static
    {
        $this->except = $fieldCodes;

        return $this;
    }

    public function only(array $fieldCodes): static
    {
        $this->only = $fieldCodes;

        return $this;
    }

    /**
     * Constrain resolution to the given custom field sections.
     *
     * Field codes are unique per section, not globally, in consumers that version their
     * sections. Scoping structurally lets two sections carry the same code without one
     * bleeding into the other's schema.
     *
     * IMPORTANT: this only narrows resolution (what gets loaded onto the form/infolist).
     * It does not change how UsesCustomFields::saveCustomFields() saves — that method
     * writes by code against the model's customFields() relationship. If two sections
     * share a code and customFields() isn't scoped to match, saveCustomFields() will
     * write the same submitted value to both field rows. Scoping resolution alone is not
     * sufficient; the model's customFields() relation must be scoped to the same
     * section(s) too. See the "Builder Scoping" docs page.
     *
     * @param  array<int, int>  $sectionIds
     */
    public function onlySections(array $sectionIds): static
    {
        $this->onlySections = $sectionIds;

        return $this;
    }

    /**
     * @return Collection<int, CustomFieldSection>
     */
    protected function getFilteredSections(): Collection
    {
        // Return empty collection when sections are disabled
        if (! $this->sections instanceof Builder) {
            return collect();
        }

        /** @var Collection<int, CustomFieldSection> $sections */
        $sections = $this->sections
            ->when($this->onlySections !== [], fn (Builder $query): Builder => $query->whereIn(
                $this->sections->getModel()->getQualifiedKeyName(),
                $this->onlySections
            ))
            ->with(['fields' => function (mixed $query): mixed {
                return $query
                    ->when($this instanceof TableBuilder, fn (CustomFieldQueryBuilder $q, bool $condition): CustomFieldQueryBuilder => $q->visibleInList())
                    ->when($this instanceof InfolistBuilder, fn (CustomFieldQueryBuilder $q, bool $condition): CustomFieldQueryBuilder => $q->visibleInView())
                    ->when($this->only !== [], fn (CustomFieldQueryBuilder $q, bool $condition): CustomFieldQueryBuilder => $q->whereIn('code', $this->only))
                    ->when($this->except !== [], fn (CustomFieldQueryBuilder $q, bool $condition): CustomFieldQueryBuilder => $q->whereNotIn('code', $this->except))
                    ->with('options')
                    ->orderBy('sort_order');
            }])
            ->get();

        return $sections
            ->map(function (CustomFieldSection $section): CustomFieldSection {
                $section->setRelation('fields', $section->fields->filter(fn (CustomField $field): bool => $field->typeData !== null));

                return $section;
            })
            ->filter(fn (CustomFieldSection $section) => $section->fields->isNotEmpty());
    }

    /**
     * Get all fields directly, bypassing the section table.
     * More efficient when simplified management mode is enabled.
     *
     * @return Collection<int, CustomField>
     */
    protected function getFieldsDirectly(): Collection
    {
        /*
         * custom_field_section_id only exists on the table when SYSTEM_SECTIONS was
         * enabled at migration time, and this method is exclusively the sections-disabled
         * path (see getAllFields()). A section scope can never match anything here — there
         * is no section table to resolve it against — so return empty rather than filter
         * by a column that may not exist.
         */
        if ($this->onlySections !== [] && ! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)) {
            return collect();
        }

        return CustomFields::newCustomFieldModel()::forMorphEntity($this->model::class)
            ->when($this instanceof TableBuilder, fn (CustomFieldQueryBuilder $q): CustomFieldQueryBuilder => $q->visibleInList())
            ->when($this instanceof InfolistBuilder, fn (CustomFieldQueryBuilder $q): CustomFieldQueryBuilder => $q->visibleInView())
            ->when($this->only !== [], fn (CustomFieldQueryBuilder $q): CustomFieldQueryBuilder => $q->whereIn('code', $this->only))
            ->when($this->except !== [], fn (CustomFieldQueryBuilder $q): CustomFieldQueryBuilder => $q->whereNotIn('code', $this->except))
            ->with('options')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (CustomField $field): bool => $field->typeData !== null);
    }

    /**
     * Get all fields, using the most efficient method based on configuration.
     * Uses direct query when sections disabled, otherwise extracts from sections.
     *
     * @return Collection<int, CustomField>
     */
    protected function getAllFields(): Collection
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)) {
            return $this->getFieldsDirectly();
        }

        return $this->getFilteredSections()->flatMap(
            fn (CustomFieldSection $section): Collection => $section->fields
        );
    }
}
