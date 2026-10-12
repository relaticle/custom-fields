<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Concerns;

trait ConfiguresIdentity
{
    private string $key = '';

    private string $label = '';

    private string $icon = '';

    /**
     * Set the field key
     */
    public function key(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    /**
     * Set the field label
     */
    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * Set the field icon
     */
    public function icon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * Get the field key
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get the field label
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * Get the field icon
     */
    public function getIcon(): string
    {
        return $this->icon;
    }
}
