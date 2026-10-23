<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem;

use Closure;

/**
 * System-wide configuration for the entire field type system
 * Provides clean, discoverable API for field type configuration and management
 */
final class FieldTypeConfigurator
{
    private bool $autoDiscover = true;

    private bool $cacheEnabled = true;

    private int $cacheTtl = 3600;

    private string $cacheStore = 'default';

    /** @var array<int, string> */
    private array $cacheTags = ['field-types', 'configuration'];

    /** @var array<int, string> */
    private array $enabledFieldTypes = [];

    /** @var array<int, string> */
    private array $disabledFieldTypes = [];

    private function __construct()
    {
        //
    }

    /**
     * Start building field type configuration
     */
    public static function configure(): self
    {
        return new self;
    }

    /**
     * Enable/disable automatic discovery of field types
     */
    public function discover(bool $enabled = true): self
    {
        $this->autoDiscover = $enabled;

        return $this;
    }

    /**
     * Configure caching settings
     *
     * @param  array<int, string>  $tags
     */
    public function cache(bool $enabled = true, int $ttl = 3600, ?string $store = null, array $tags = []): self
    {
        $this->cacheEnabled = $enabled;
        $this->cacheTtl = $ttl;

        if ($store !== null) {
            $this->cacheStore = $store;
        }

        if ($tags !== []) {
            $this->cacheTags = $tags;
        }

        return $this;
    }

    /**
     * Add conditional configuration
     */
    public function when(bool $condition, Closure $callback): self
    {
        if ($condition) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Enable only specific field types (empty array = all enabled)
     *
     * @param  array<int, string>  $fieldTypes
     */
    public function enabled(array $fieldTypes = []): self
    {
        $this->enabledFieldTypes = $fieldTypes;

        return $this;
    }

    /**
     * Disable specific field types
     *
     * @param  array<int, string>  $fieldTypes
     */
    public function disabled(array $fieldTypes = []): self
    {
        $this->disabledFieldTypes = $fieldTypes;

        return $this;
    }

    /**
     * Check if a field type is allowed based on enabled/disabled configuration
     */
    public function isFieldTypeAllowed(string $fieldTypeKey): bool
    {
        // If enabled list is specified, only allow those
        if ($this->enabledFieldTypes !== []) {
            return in_array($fieldTypeKey, $this->enabledFieldTypes);
        }

        // If disabled list is specified, exclude those
        if ($this->disabledFieldTypes !== []) {
            return ! in_array($fieldTypeKey, $this->disabledFieldTypes);
        }

        // Default: allow all field types
        return true;
    }

    /**
     * Restore the configurator from var_export
     *
     * @param  array<string, mixed>  $properties
     */
    public static function __set_state(array $properties): self
    {
        $instance = new self;

        foreach ($properties as $property => $value) {
            $instance->$property = $value;
        }

        return $instance;
    }
}
