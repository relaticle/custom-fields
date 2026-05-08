<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;

/**
 * Custom fields activable scope that also checks section activation.
 */
class CustomFieldsActivableScope extends ActivableScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param  Builder<covariant Model>  $builder
     */
    #[Override]
    public function apply(Builder $builder, Model $model): void
    {
        if (! method_exists($model, 'getQualifiedActiveColumn')) {
            return;
        }

        $builder->where($model->getQualifiedActiveColumn(), true);

        // Only check section activation when sections are enabled
        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)) {
            $builder->whereHas('section', function (Builder $query): void {
                /** @phpstan-ignore-next-line */
                $query->active();
            });
        }
    }
}
