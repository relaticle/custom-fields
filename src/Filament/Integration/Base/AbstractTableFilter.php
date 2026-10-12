<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Base;

use Closure;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Contracts\TableFilterInterface;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Support\ThroughRelationResolver;

/**
 * ABOUTME: Abstract base class for table filter components providing common structure.
 * ABOUTME: Standardizes filter creation pattern across different filter types.
 */
abstract class AbstractTableFilter implements TableFilterInterface
{
    /**
     * Create and configure a table filter.
     */
    abstract public function make(CustomField $customField, ?Model $record = null, ?string $through = null): BaseFilter;

    /**
     * Run a constraint written against the model that owns the field. Without a through path
     * that is the row model itself, so both cases share one query shape.
     *
     * @param  Builder<Model>  $query
     * @param  Closure(Builder<Model>): Builder<Model>  $constraint
     * @return Builder<Model>
     */
    protected function constrainThrough(Builder $query, ?string $through, Closure $constraint): Builder
    {
        if ($through === null) {
            return $constraint($query);
        }

        return app(ThroughRelationResolver::class)->constrain($query, $through, $constraint);
    }

    protected function hasColorOptionsEnabled(CustomField $customField): bool
    {
        return FeatureManager::isEnabled(CustomFieldsFeature::FIELD_OPTION_COLORS)
            && $customField->settings->enable_option_colors;
    }
}
