<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\ValueResolver;

/**
 * Request-scoped cache of resolved lookup titles keyed by (lookup_type, id).
 *
 * Populated either lazily by LookupResolver on demand, or eagerly by the
 * scopeWithCustomFieldValues afterQuery hook. Either way, downstream column
 * and infolist renders hit the cache instead of firing one query per row.
 */
final class LookupCache
{
    /** @var array<string, array<int|string, string>> */
    private array $titles = [];

    public function titleFor(string $lookupType, int|string $id): ?string
    {
        return $this->titles[$lookupType][$id] ?? null;
    }

    /**
     * @param  array<int|string, string>  $map  id => title
     */
    public function remember(string $lookupType, array $map): void
    {
        $this->titles[$lookupType] = ($this->titles[$lookupType] ?? []) + $map;
    }

    /**
     * Return the subset of IDs that have not been cached yet.
     *
     * @param  array<int, int|string>  $ids
     * @return array<int, int|string>
     */
    public function missing(string $lookupType, array $ids): array
    {
        $cached = $this->titles[$lookupType] ?? [];

        return array_values(array_filter(
            array_unique($ids),
            static fn (int|string $id): bool => ! array_key_exists($id, $cached),
        ));
    }

    public function flush(): void
    {
        $this->titles = [];
    }
}
