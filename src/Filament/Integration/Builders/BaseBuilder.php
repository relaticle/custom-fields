<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\QueryBuilders\CustomFieldQueryBuilder;

abstract class BaseBuilder
{
    protected Model&HasCustomFields $model;

    protected Model|string|null $explicitModel = null;

    protected Builder $sections;

    protected array $except = [];

    protected array $only = [];

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

        $this->sections = CustomFields::newSectionModel()->query()
            ->forEntityType($model::class)
            ->orderBy('sort_order');

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
     * @return Collection<int, CustomFieldSection>
     */
    protected function getFilteredSections(): Collection
    {
        /** @var Collection<int, CustomFieldSection> $sections */
        $sections = $this->sections
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
}
