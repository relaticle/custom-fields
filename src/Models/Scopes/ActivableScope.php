<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ActivableScope implements Scope
{
    /**
     * All the extensions to be added to the builder.
     *
     * @var string[]
     */
    protected array $extensions = ['active', 'WithDeactivated', 'WithoutDeactivated'];

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (method_exists($model, 'getQualifiedActiveColumn')) {
            $builder->where($model->getQualifiedActiveColumn(), true);
        }
    }

    /**
     * Extend the query builder with the needed functions.
     *
     * @param Builder<*> $builder
     */
    public function extend(Builder $builder): void
    {
        foreach ($this->extensions as $extension) {
            $methodName = 'add'.$extension;
            if (method_exists($this, $methodName)) {
                $this->$methodName($builder);
            }
        }
    }

    /**
     * @param  Builder<Model>  $builder
     */
    protected function addActive(Builder $builder): void
    {
        $builder->macro('active', function (Builder $builder): Builder {
            $model = $builder->getModel();
            if (method_exists($model, 'getQualifiedActiveColumn')) {
                return $builder->where($model->getQualifiedActiveColumn(), true);
            }

            return $builder;
        });
    }

    /**
     * Add the with-trashed extension to the builder.
     *
     * @param Builder<*> $builder
     */
    protected function addWithDeactivated(Builder $builder): void
    {
        $scope = $this;
        $builder->macro('withDeactivated', function (Builder $builder, bool $withDeactivated = true) use ($scope): Builder {
            if (! $withDeactivated) {
                $model = $builder->getModel();
                if (method_exists($model, 'getQualifiedActiveColumn')) {
                    return $builder->where($model->getQualifiedActiveColumn(), true);
                }

                return $builder;
            }

            return $builder->withoutGlobalScope($scope);
        });
    }

    /**
     * Add the without-trashed extension to the builder.
     *
     * @param Builder<*> $builder
     */
    protected function addWithoutDeactivated(Builder $builder): void
    {
        $scope = $this;
        $builder->macro('withoutDeactivated', function (Builder $builder) use ($scope): Builder {
            $model = $builder->getModel();

            if (method_exists($model, 'getQualifiedActiveColumn')) {
                $builder->withoutGlobalScope($scope)->whereNull(
                    $model->getQualifiedActiveColumn()
                );
            }

            return $builder;
        });
    }
}
