<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class SortOrderScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->orderBy('sort_order');
    }
}
