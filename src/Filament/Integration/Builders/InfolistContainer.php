<?php

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Model;

final class InfolistContainer extends Grid
{
    private Model|string|null $explicitModel = null;

    private array $except = [];

    private array $only = [];

    private bool $hiddenLabels = false;

    private bool $visibleWhenFilled = false;

    private bool $withoutSections = false;

    public static function make(array|int|null $columns = 12): static
    {
        $container = new self($columns);

        // Defer schema generation until component is in container
        $container->schema(fn (): array => $container->generateSchema());

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

        $builder = app(InfolistBuilder::class)
            ->forModel($model)
            ->only($this->only)
            ->except($this->except)
            ->hiddenLabels($this->hiddenLabels)
            ->visibleWhenFilled($this->visibleWhenFilled)
            ->withoutSections($this->withoutSections);

        return $builder->values()->toArray();
    }
}
