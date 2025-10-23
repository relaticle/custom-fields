<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Concerns;

use Exception;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Facades\CustomFields;

trait InteractsWithCustomFields
{
    /**
     * @throws BindingResolutionException
     */
    public function table(Table $table): Table
    {
        $model = $this instanceof RelationManager ? $this->getRelationship()->getModel()::class : $this->getModel();

        try {
            $table = static::getResource()::table($table);
        } catch (Exception) {
            $table = parent::table($table);
        }

        // Use the new builder API
        $modelInstance = new $model;
        $columns = CustomFields::table()->forModel($modelInstance)->columns()->toArray();
        $filters = CustomFields::table()->forModel($modelInstance)->filters()->toArray();

        return $table->modifyQueryUsing(function (Builder $query): void {
            $query->with('customFieldValues.customField');
        })
            ->pushColumns($columns)
            ->pushFilters($filters);
    }
}
