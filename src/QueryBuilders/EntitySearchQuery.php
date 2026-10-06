<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\QueryBuilders;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use function Filament\Support\generate_search_column_expression;
use function Filament\Support\generate_search_term_expression;

/**
 * Search a lookup entity by the attributes its own configuration declares, following
 * Filament's global search rules for term splitting and case so a record lookup answers
 * the same as the resource it points at.
 */
final readonly class EntitySearchQuery
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string|array<int, string>>  $attributes
     * @param  ?class-string  $resourceClass
     * @return Builder<TModel>
     */
    public function apply(Builder $query, string $search, array $attributes, ?string $resourceClass = null): Builder
    {
        if ($attributes === []) {
            return $query;
        }

        $connection = $query->getModel()->getConnection();
        $forcedCaseInsensitive = $this->isForcedCaseInsensitive($resourceClass);
        $term = generate_search_term_expression($search, $forcedCaseInsensitive, $connection);

        foreach ($this->terms($term, $resourceClass) as $word) {
            $query->where(fn (Builder $nested): Builder => $this->matchAnyAttribute($nested, $word, $attributes, $connection, $forcedCaseInsensitive));
        }

        return $query;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string|array<int, string>>  $attributes
     * @return Builder<TModel>
     */
    private function matchAnyAttribute(Builder $query, string $term, array $attributes, Connection $connection, ?bool $forcedCaseInsensitive): Builder
    {
        foreach ($attributes as $group) {
            foreach (Arr::wrap($group) as $attribute) {
                $this->matchAttribute($query, $term, $attribute, $connection, $forcedCaseInsensitive);
            }
        }

        return $query;
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function matchAttribute(Builder $query, string $term, string $attribute, Connection $connection, ?bool $forcedCaseInsensitive): void
    {
        if (! str_contains($attribute, '.')) {
            $query->orWhere(
                generate_search_column_expression($query->qualifyColumn($attribute), $forcedCaseInsensitive, $connection),
                'like',
                sprintf('%%%s%%', $term),
            );

            return;
        }

        $query->orWhereHas(
            Str::beforeLast($attribute, '.'),
            fn (Builder $related): Builder => $related->where(
                generate_search_column_expression($related->qualifyColumn(Str::afterLast($attribute, '.')), $forcedCaseInsensitive, $connection),
                'like',
                sprintf('%%%s%%', $term),
            ),
        );
    }

    /**
     * Filament ANDs the words of a split term and ORs the attributes inside each word.
     *
     * @param  ?class-string  $resourceClass
     * @return array<int, string>
     */
    private function terms(string $term, ?string $resourceClass): array
    {
        if (! $this->shouldSplit($resourceClass)) {
            return [$term];
        }

        $words = str_getcsv(
            (string) preg_replace('/(\s|\x{3164}|\x{1160})+/u', ' ', $term),
            separator: ' ',
            escape: '\\',
        );

        $words = array_values(array_filter($words, static fn (?string $word): bool => filled($word)));

        return $words === [] ? [$term] : $words;
    }

    /**
     * @param  ?class-string  $resourceClass
     */
    private function isForcedCaseInsensitive(?string $resourceClass): ?bool
    {
        if ($resourceClass === null || ! method_exists($resourceClass, 'isGlobalSearchForcedCaseInsensitive')) {
            return null;
        }

        return $resourceClass::isGlobalSearchForcedCaseInsensitive();
    }

    /**
     * @param  ?class-string  $resourceClass
     */
    private function shouldSplit(?string $resourceClass): bool
    {
        if ($resourceClass === null || ! method_exists($resourceClass, 'shouldSplitGlobalSearchTerms')) {
            return true;
        }

        return $resourceClass::shouldSplitGlobalSearchTerms();
    }
}
