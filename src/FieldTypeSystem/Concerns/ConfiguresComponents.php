<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Concerns;

use Closure;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

trait ConfiguresComponents
{
    private string|Closure|null $formComponent = null;

    private string|Closure|null $tableColumn = null;

    private string|Closure|null $tableFilter = null;

    private string|Closure|null $infolistEntry = null;

    private int $priority = 500;

    private ?string $settingsDataClass = null;

    private string|Closure|null $settingsSchema = null;

    /**
     * Set the form component for this field type
     */
    public function formComponent(string|Closure $component): self
    {
        $this->formComponent = $component;

        return $this;
    }

    /**
     * Set the table column for this field type
     */
    public function tableColumn(string|Closure $column): self
    {
        $this->tableColumn = $column;

        return $this;
    }

    /**
     * Set the table filter for this field type
     */
    public function tableFilter(string|Closure $filter): self
    {
        $this->tableFilter = $filter;

        return $this;
    }

    /**
     * Set the infolist entry for this field type
     */
    public function infolistEntry(string|Closure $entry): self
    {
        $this->infolistEntry = $entry;

        return $this;
    }

    /**
     * Set the priority for field ordering
     */
    public function priority(int $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function withSettings(string $dataClass, string|Closure $schema): self
    {
        if (! is_subclass_of($dataClass, Data::class)) {
            throw new InvalidArgumentException('Settings data class must extend '.Data::class);
        }

        $this->settingsDataClass = $dataClass;
        $this->settingsSchema = $schema;

        return $this;
    }

    /**
     * Get the form component
     */
    public function getFormComponent(): string|Closure|null
    {
        return $this->formComponent;
    }

    /**
     * Get the priority
     */
    public function getPriority(): int
    {
        return $this->priority;
    }
}
