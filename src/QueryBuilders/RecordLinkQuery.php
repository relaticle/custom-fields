<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\QueryBuilders;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Models\CustomFieldRelationship;

/**
 * Filter, search, and sort a host table by the edges of one relationship definition.
 * Every clause is an indexed correlated subquery, so none of them read json_value.
 */
final readonly class RecordLinkQuery
{
    public function __construct(private EntitySearchQuery $entitySearch) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, mixed>  $targetIds
     * @return Builder<TModel>
     */
    public function whereLinkedTo(Builder $query, CustomFieldRelationship $definition, string $direction, array $targetIds): Builder
    {
        $host = $query->getModel();

        return $query->where(function (Builder $outer) use ($definition, $direction, $host, $targetIds): void {
            foreach ($this->ends($direction) as $end) {
                $outer->orWhereExists(function (QueryBuilder $sub) use ($definition, $host, $end, $targetIds): void {
                    $this->edge($sub, $definition, $host, $end)
                        ->whereIn($this->column($this->opposite($end).'_entity_id'), $targetIds);
                });
            }
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $searchAttributes
     * @return Builder<TModel>
     */
    public function whereLinkedMatching(Builder $query, CustomFieldRelationship $definition, string $direction, array $searchAttributes, string $search): Builder
    {
        $target = $this->targetModel($definition, $direction);

        if (! $target instanceof Model || $searchAttributes === []) {
            return $query;
        }

        $host = $query->getModel();

        // The matching-ids subquery is uncorrelated, so it needs no alias even when the
        // host and the target are the same table.
        $matches = $this->entitySearch
            ->apply($target->newQuery(), $search, $searchAttributes, Filament::getModelResource($target::class))
            ->select($target->qualifyColumn($target->getKeyName()))
            ->getQuery();

        return $query->where(function (Builder $outer) use ($definition, $direction, $host, $matches): void {
            foreach ($this->ends($direction) as $end) {
                $outer->orWhereExists(function (QueryBuilder $sub) use ($definition, $host, $end, $matches): void {
                    $this->edge($sub, $definition, $host, $end)
                        ->whereIn($this->column($this->opposite($end).'_entity_id'), $matches);
                });
            }
        });
    }

    /**
     * Ordering reads the first linked record's primary attribute through two scalar
     * subqueries. The target is aliased because a self relationship would otherwise
     * shadow the host row the inner query correlates against.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function orderByLinkedAttribute(Builder $query, CustomFieldRelationship $definition, string $direction, string $attribute, string $sortDirection): Builder
    {
        $target = $this->targetModel($definition, $direction);

        if (! $target instanceof Model) {
            return $query;
        }

        $host = $query->getModel();
        $alias = 'custom_field_link_target';

        $linkedId = DB::table($this->table())
            ->select(new Expression($this->linkedIdExpression($query, $direction, $host)))
            ->where($this->column('relationship_id'), $definition->getKey())
            ->whereNull($this->column('active_until'))
            ->where(fn (QueryBuilder $nested): QueryBuilder => $this->matchHost($nested, $direction, $host))
            ->orderBy($this->column('sort_order'))
            ->limit(1);

        $linkedAttribute = DB::table($target->getTable().' as '.$alias)
            ->select($alias.'.'.$attribute)
            ->where($alias.'.'.$target->getKeyName(), '=', $linkedId)
            ->limit(1);

        $sql = $linkedAttribute->toSql();
        $bindings = $linkedAttribute->getBindings();

        // Unlinked rows sort last in both directions. The leading term says so without a
        // NULLS LAST clause, which the MySQL family does not have.
        return $query->orderByRaw(
            sprintf('(%s) is null asc, (%s) %s', $sql, $sql, $this->sortDirection($sortDirection)),
            [...$bindings, ...$bindings],
        );
    }

    private function sortDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    private function matchHost(QueryBuilder $nested, string $direction, Model $host): QueryBuilder
    {
        foreach ($this->ends($direction) as $end) {
            $nested->orWhere(fn (QueryBuilder $side): QueryBuilder => $side
                ->where($this->column($end.'_entity_type'), $host->getMorphClass())
                ->whereColumn($this->column($end.'_entity_id'), $host->qualifyColumn($host->getKeyName())));
        }

        return $nested;
    }

    private function edge(QueryBuilder $sub, CustomFieldRelationship $definition, Model $host, string $end): QueryBuilder
    {
        return $sub->select(new Expression('1'))
            ->from($this->table())
            ->where($this->column('relationship_id'), $definition->getKey())
            ->whereNull($this->column('active_until'))
            ->where($this->column($end.'_entity_type'), $host->getMorphClass())
            ->whereColumn($this->column($end.'_entity_id'), $host->qualifyColumn($host->getKeyName()));
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function linkedIdExpression(Builder $query, string $direction, Model $host): string
    {
        $grammar = $query->getQuery()->getGrammar();

        if ($direction !== CustomFieldRelationship::DIRECTION_BOTH) {
            return $grammar->wrap($this->column($this->opposite($direction).'_entity_id'));
        }

        return sprintf(
            'case when %s = %s then %s else %s end',
            $grammar->wrap($this->column('from_entity_id')),
            $grammar->wrap($host->qualifyColumn($host->getKeyName())),
            $grammar->wrap($this->column('to_entity_id')),
            $grammar->wrap($this->column('from_entity_id')),
        );
    }

    private function targetModel(CustomFieldRelationship $definition, string $direction): ?Model
    {
        $entityType = $direction === CustomFieldRelationship::DIRECTION_TO
            ? $definition->from_entity_type
            : $definition->to_entity_type;

        $entityClass = Relation::getMorphedModel($entityType) ?? $entityType;

        if (! class_exists($entityClass) || ! is_subclass_of($entityClass, Model::class)) {
            return null;
        }

        return new $entityClass;
    }

    /**
     * @return array<int, string>
     */
    private function ends(string $direction): array
    {
        return match ($direction) {
            CustomFieldRelationship::DIRECTION_FROM => [CustomFieldRelationship::DIRECTION_FROM],
            CustomFieldRelationship::DIRECTION_TO => [CustomFieldRelationship::DIRECTION_TO],
            default => [CustomFieldRelationship::DIRECTION_FROM, CustomFieldRelationship::DIRECTION_TO],
        };
    }

    private function opposite(string $end): string
    {
        return $end === CustomFieldRelationship::DIRECTION_FROM
            ? CustomFieldRelationship::DIRECTION_TO
            : CustomFieldRelationship::DIRECTION_FROM;
    }

    private function column(string $column): string
    {
        return $this->table().'.'.$column;
    }

    private function table(): string
    {
        return (string) config('custom-fields.database.table_names.custom_field_links');
    }
}
