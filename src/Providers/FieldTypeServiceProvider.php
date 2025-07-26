<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Providers;

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;

final class FieldTypeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->modifyQueryUsing(function (Builder $query): void {
                $query->when($query->getModel() instanceof HasCustomFields, fn (Builder $q) => $q->with('customFieldValues.customField'));
            });
        });
    }
}
