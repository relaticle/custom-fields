<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Livewire;

use Closure;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

/**
 * A table over any row model, configured by the test that mounts it. The three to-one
 * relation kinds need three different row models, and none of them has a resource.
 */
final class ThroughTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** @var (Closure(Table): Table)|null */
    public static ?Closure $configureUsing = null;

    public function table(Table $table): Table
    {
        if (! self::$configureUsing instanceof Closure) {
            throw new RuntimeException('Set ThroughTable::$configureUsing before mounting the component.');
        }

        return (self::$configureUsing)($table);
    }

    public function render(): View
    {
        return view()->file(__DIR__.'/through-table.blade.php');
    }
}
