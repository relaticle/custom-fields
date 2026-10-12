<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Relaticle\CustomFields\Exceptions\UnsupportedThroughRelationException;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Decide whether a row model can reach custom fields through one of its relations.
 * Every table surface asks here, so the state, sort, search and filter paths cannot
 * disagree about what a through path supports.
 */
final readonly class ThroughRelationResolver
{
    /**
     * @return Relation<Model, Model, ?Model>
     *
     * @throws UnsupportedThroughRelationException
     */
    public function resolve(Model $model, string $relation): Relation
    {
        $instance = $this->relationInstance($model, $relation);

        if (! $instance instanceof Relation) {
            throw UnsupportedThroughRelationException::missing($model::class, $relation);
        }

        if ($instance instanceof MorphTo) {
            throw UnsupportedThroughRelationException::polymorphicTarget($model::class, $relation);
        }

        if (! $instance instanceof BelongsTo && ! $instance instanceof HasOne && ! $instance instanceof MorphOne) {
            throw UnsupportedThroughRelationException::toMany($model::class, $relation, $instance::class);
        }

        $related = $instance->getRelated();

        if (! $related instanceof HasCustomFields) {
            throw UnsupportedThroughRelationException::withoutCustomFields($model::class, $relation, $related::class);
        }

        return $instance;
    }

    /**
     * The related record a field is read from, or null when the row has none.
     *
     * @return (Model&HasCustomFields)|null
     *
     * @throws UnsupportedThroughRelationException
     */
    public function relatedRecord(Model $record, string $relation): ?Model
    {
        $this->resolve($record, $relation);

        $related = $record->getAttribute($relation);

        return $related instanceof Model && $related instanceof HasCustomFields ? $related : null;
    }

    /**
     * Apply a constraint written against the related model to a row query.
     *
     * @param  Builder<Model>  $query
     * @param  Closure(Builder<Model>): mixed  $constraint
     * @return Builder<Model>
     *
     * @throws UnsupportedThroughRelationException
     */
    public function constrain(Builder $query, string $relation, Closure $constraint): Builder
    {
        $this->resolve($query->getModel(), $relation);

        return $query->whereHas($relation, $constraint);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     *
     * @throws UnsupportedThroughRelationException
     */
    public function orderByFieldValue(Builder $query, string $relation, CustomField $customField, string $direction): Builder
    {
        $instance = $this->resolve($query->getModel(), $relation);

        $keys = $instance->getRelationExistenceQuery(
            $instance->getRelated()->newQueryWithoutRelationships(),
            $query,
        );

        // The order has to see the rows the cell sees: the related model's global scopes and
        // whatever the relation body constrains, which is what whereHas() merges as well.
        $keys->mergeConstraintsFrom($instance->getQuery());

        $values = $customField->values();

        $values
            ->select($customField->getValueColumn())
            ->whereIn(
                $values->getRelated()->qualifyColumn('entity_id'),
                // A self relation aliases the inner table, and only the returned builder
                // knows the alias, so the key column is chosen after the correlation.
                $keys->select($keys->getModel()->getQualifiedKeyName()),
            )
            ->limit(1);

        $value = $values->getQuery();
        $sql = sprintf('(%s)', $value->toSql());

        // Rows with no related record, or none the relation admits, sort last in both
        // directions. The leading term says so without a NULLS LAST clause, which the MySQL
        // family does not have.
        return $query->orderByRaw(
            sprintf('%s is null asc, %s %s', $sql, $sql, $this->sortDirection($direction)),
            [...$value->getBindings(), ...$value->getBindings()],
        );
    }

    private function sortDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @return Relation<Model, Model, ?Model>|null
     */
    private function relationInstance(Model $model, string $relation): ?Relation
    {
        if (! $model->isRelation($relation)) {
            return null;
        }

        // Built the way an existence query is built: without the parent-key constraint, so the
        // relation body's own wheres are all that travels with it.
        /** @var Relation<Model, Model, ?Model>|mixed $instance */
        $instance = Relation::noConstraints(fn (): mixed => $model->{$relation}());

        return $instance instanceof Relation ? $instance : null;
    }
}
