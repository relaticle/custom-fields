<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Contracts\ModelResourceResolverInterface;
use Relaticle\CustomFields\Data\LinkEnd;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;

/**
 * Filter, search, and sort a host table by the edges of one relationship definition.
 * Every clause is an indexed correlated subquery, so none of them read json_value.
 */
final readonly class RecordLinkQuery
{
    public function __construct(
        private EntitySearchQuery $entitySearch,
        private ModelResourceResolverInterface $resources,
    ) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, mixed>  $targetIds
     * @return Builder<TModel>
     */
    public function whereLinkedTo(Builder $query, CustomField $field, array $targetIds): Builder
    {
        $definition = $field->relationshipDefinitionOrFail();
        $host = $query->getModel();

        return $query->where(function (Builder $outer) use ($definition, $field, $host, $targetIds): void {
            foreach ($definition->readEnds($field) as $end) {
                $outer->orWhereExists(function (QueryBuilder $sub) use ($definition, $host, $end, $targetIds): void {
                    $this->edge($sub, $definition, $host, $end)
                        ->whereIn($this->column($end->side->opposite()->idColumn()), $targetIds);
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
    public function whereLinkedMatching(Builder $query, CustomField $field, array $searchAttributes, string $search): Builder
    {
        $definition = $field->relationshipDefinitionOrFail();
        $target = $this->targetModel($definition->farEnd($field));

        if (! $target instanceof Model || $searchAttributes === []) {
            return $query;
        }

        $host = $query->getModel();

        // The matching-ids subquery is uncorrelated, so it needs no alias even when the
        // host and the target are the same table.
        $matches = $this->entitySearch
            ->apply($target->newQuery(), $search, $searchAttributes, $this->resources->resourceFor($target::class))
            ->select($target->qualifyColumn($target->getKeyName()))
            ->getQuery();

        return $query->where(function (Builder $outer) use ($definition, $field, $host, $matches): void {
            foreach ($definition->readEnds($field) as $end) {
                $outer->orWhereExists(function (QueryBuilder $sub) use ($definition, $host, $end, $matches): void {
                    $this->edge($sub, $definition, $host, $end)
                        ->whereIn($this->column($end->side->opposite()->idColumn()), $matches);
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
    public function orderByLinkedAttribute(Builder $query, CustomField $field, string $attribute, string $sortDirection): Builder
    {
        $definition = $field->relationshipDefinitionOrFail();
        $ends = $definition->readEnds($field);
        $target = $this->targetModel($definition->farEnd($field));

        if (! $target instanceof Model) {
            return $query;
        }

        $host = $query->getModel();
        $alias = 'custom_field_link_target';

        $linkedId = DB::table($this->table())
            ->select(new Expression($this->linkedIdExpression($query, $ends, $host)))
            ->where($this->column('relationship_id'), $definition->getKey())
            ->whereNull($this->column('active_until'))
            ->where(fn (QueryBuilder $nested): QueryBuilder => $this->matchHost($nested, $ends, $host))
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

    /**
     * @param  list<LinkEnd>  $ends
     */
    private function matchHost(QueryBuilder $nested, array $ends, Model $host): QueryBuilder
    {
        foreach ($ends as $end) {
            $nested->orWhere(fn (QueryBuilder $side): QueryBuilder => $side
                ->where($this->column($end->typeColumn()), $host->getMorphClass())
                ->whereColumn($this->column($end->idColumn()), $host->qualifyColumn($host->getKeyName())));
        }

        return $nested;
    }

    private function edge(QueryBuilder $sub, CustomFieldRelationship $definition, Model $host, LinkEnd $end): QueryBuilder
    {
        return $sub->select(new Expression('1'))
            ->from($this->table())
            ->where($this->column('relationship_id'), $definition->getKey())
            ->whereNull($this->column('active_until'))
            ->where($this->column($end->typeColumn()), $host->getMorphClass())
            ->whereColumn($this->column($end->idColumn()), $host->qualifyColumn($host->getKeyName()));
    }

    /**
     * @param  Builder<covariant Model>  $query
     * @param  list<LinkEnd>  $ends
     */
    private function linkedIdExpression(Builder $query, array $ends, Model $host): string
    {
        $grammar = $query->getQuery()->getGrammar();

        if (count($ends) === 1) {
            return $grammar->wrap($this->column($ends[0]->side->opposite()->idColumn()));
        }

        return sprintf(
            'case when %s = %s then %s else %s end',
            $grammar->wrap($this->column('from_entity_id')),
            $grammar->wrap($host->qualifyColumn($host->getKeyName())),
            $grammar->wrap($this->column('to_entity_id')),
            $grammar->wrap($this->column('from_entity_id')),
        );
    }

    private function targetModel(LinkEnd $target): ?Model
    {
        $entityClass = Relation::getMorphedModel($target->entityType) ?? $target->entityType;

        if (! class_exists($entityClass) || ! is_subclass_of($entityClass, Model::class)) {
            return null;
        }

        return new $entityClass;
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
