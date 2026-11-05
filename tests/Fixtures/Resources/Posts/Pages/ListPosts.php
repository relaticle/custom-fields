<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Relaticle\CustomFields\Concerns\InteractsWithCustomFields;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;

class ListPosts extends ListRecords
{
    use InteractsWithCustomFields;

    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
