<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

final class RelationConditionResolver
{
    /**
     * Resolve a dotted relation path on a record to the unique set of
     * terminal related-record keys, as strings.
     *
     * @return array<int, string>
     */
    public function resolveRelatedKeys(Model $record, string $path): array
    {
        $current = new Collection([$record]);

        foreach (explode('.', $path) as $segment) {
            $current = $current->flatMap(function (mixed $model) use ($segment): array {
                if (! $model instanceof Model) {
                    return [];
                }

                $related = $model->getAttribute($segment);

                if ($related === null) {
                    return [];
                }

                return $related instanceof Collection ? $related->all() : [$related];
            });
        }

        return $current
            ->filter(fn (mixed $model): bool => $model instanceof Model)
            ->map(fn (Model $model): string => (string) $model->getKey())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Reflect the terminal related model of a path without hitting the DB.
     * Returns null if any segment is not a valid relation.
     */
    public function resolveTerminalRelatedModel(string $entityClass, string $path): ?Model
    {
        try {
            /** @var Model $model */
            $model = new $entityClass;

            foreach (explode('.', $path) as $segment) {
                $model = $model->{$segment}()->getRelated();
            }

            return $model;
        } catch (Throwable) {
            return null;
        }
    }
}
