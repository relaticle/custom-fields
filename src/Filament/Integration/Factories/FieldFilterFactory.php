<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Closure;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;
use Relaticle\CustomFields\Models\CustomField;

class FieldFilterFactory
{
    /**
     * @throws BindingResolutionException
     */
    public function create(CustomField $customField): BaseFilter
    {
        $tableFilterDefinition = $customField->typeData->tableFilter;

        if ($tableFilterDefinition === null) {
            throw new InvalidArgumentException(sprintf("Field type '%s' does not support table filters.", $customField->type));
        }

        // Handle inline component (Closure)
        if ($tableFilterDefinition instanceof Closure) {
            return $tableFilterDefinition($customField);
        }

        // Handle traditional component class
        $component = app($tableFilterDefinition);

        return $component->make($customField);
    }
}
