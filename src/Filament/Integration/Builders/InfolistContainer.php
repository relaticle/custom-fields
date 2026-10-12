<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;

final class InfolistContainer extends Grid
{
    private ?InfolistBuilder $builder = null;

    private Model|string|null $explicitModel = null;

    /** @var array<int, string> */
    private array $except = [];

    /** @var array<int, string> */
    private array $only = [];

    /** @var array<int, int> */
    private array $onlySections = [];

    private bool $hiddenLabels = false;

    private bool $visibleWhenFilled = false;

    private ?bool $withoutSections = null;

    public static function make(array|int|null $columns = 12): static
    {
        $container = new self($columns);

        // Defer schema generation until component is in container. Resolve the
        // component Filament is evaluating instead of capturing `$container`:
        // cloning a component copies this closure by reference, so a captured
        // `$container` would keep generating the clone's schema from the
        // original — which Filament never assigns a container to.
        $container->schema(static fn (self $component): array => $component->generateSchema());

        return $container;
    }

    public function builder(InfolistBuilder $builder): static
    {
        $this->builder = clone $builder;

        return $this;
    }

    public function forModel(Model|string|null $model): static
    {
        $this->explicitModel = $model;

        return $this;
    }

    /**
     * @param  array<int, string>  $fieldCodes
     */
    public function except(array $fieldCodes): static
    {
        $this->except = $fieldCodes;

        return $this;
    }

    /**
     * @param  array<int, string>  $fieldCodes
     */
    public function only(array $fieldCodes): static
    {
        $this->only = $fieldCodes;

        return $this;
    }

    /**
     * @param  array<int, int>  $sectionIds
     */
    public function onlySections(array $sectionIds): static
    {
        $this->onlySections = $sectionIds;

        return $this;
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

    /**
     * @return array<int, Field>
     */
    private function generateSchema(): array
    {
        // Inline priority: explicit ?? record ?? model class
        $model = $this->explicitModel ?? $this->getRecord() ?? $this->getModel();

        if ($model === null) {
            return []; // Graceful fallback
        }

        // Use explicit setting if provided, otherwise check feature flag
        $withoutSections = $this->withoutSections
            ?? ! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS);

        $builder = $this->builder instanceof InfolistBuilder ? clone $this->builder : app(InfolistBuilder::class);

        return $builder->forModel($model)
            ->only($this->only)
            ->except($this->except)
            ->onlySections($this->onlySections)
            ->hiddenLabels($this->hiddenLabels)
            ->visibleWhenFilled($this->visibleWhenFilled)
            ->withoutSections($withoutSections)
            ->values()->toArray();
    }
}
