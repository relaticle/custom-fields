<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Concerns;

use Closure;

trait ConfiguresImportExport
{
    private ?string $importExample = null;

    private ?Closure $importTransformer = null;

    private ?Closure $exportTransformer = null;

    /**
     * Set import example value for templates
     */
    public function importExample(string $example): self
    {
        $this->importExample = $example;

        return $this;
    }

    /**
     * Set custom import column transformer
     */
    public function importTransformer(Closure $transformer): self
    {
        $this->importTransformer = $transformer;

        return $this;
    }

    /**
     * Set custom export value transformer
     */
    public function exportTransformer(Closure $transformer): self
    {
        $this->exportTransformer = $transformer;

        return $this;
    }

    /**
     * Get import example
     */
    public function getImportExample(): ?string
    {
        return $this->importExample;
    }

    /**
     * Get import transformer
     */
    public function getImportTransformer(): ?Closure
    {
        return $this->importTransformer;
    }

    /**
     * Get export transformer
     */
    public function getExportTransformer(): ?Closure
    {
        return $this->exportTransformer;
    }
}
