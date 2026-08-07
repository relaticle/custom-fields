<?php

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;

final class InfolistContainer extends Grid
{
    private Model|string|null $explicitModel = null;

    private array $except = [];

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

    public function forModel(Model|string|null $model): static
    {
        $this->explicitModel = $model;

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

        $builder = app(InfolistBuilder::class)
            ->forModel($model)
            ->only($this->only)
            ->except($this->except)
            ->onlySections($this->onlySections)
            ->hiddenLabels($this->hiddenLabels)
            ->visibleWhenFilled($this->visibleWhenFilled)
            ->withoutSections($withoutSections);

        return $builder->values()->toArray();
    }
}
