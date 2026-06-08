<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

final class RelationConditionConfig
{
    public function restrictToConfigured(): bool
    {
        return (bool) config('custom-fields.visibility.restrict_to_configured', false);
    }

    /**
     * @return array<string, string> path => label
     */
    public function relationsFor(string $entityClass): array
    {
        return $this->sourcesFor($entityClass)['relations'] ?? [];
    }

    /**
     * @return array<string, string> code => label
     */
    public function attributesFor(string $entityClass): array
    {
        return $this->sourcesFor($entityClass)['attributes'] ?? [];
    }

    /**
     * Relation sources are never auto-discovered; available only when a path is
     * configured, independent of restrict_to_configured.
     */
    public function isRelationSourceAvailable(string $entityClass): bool
    {
        return $this->relationsFor($entityClass) !== [];
    }

    public function isModelAttributeSourceAvailable(string $entityClass): bool
    {
        if (! $this->restrictToConfigured()) {
            return true;
        }

        return $this->attributesFor($entityClass) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcesFor(string $entityClass): array
    {
        $sources = config('custom-fields.visibility.sources', []);

        return is_array($sources) ? ($sources[$entityClass] ?? []) : [];
    }
}
