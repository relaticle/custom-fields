<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;

class ViewPost extends ViewRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function refreshTitle()
    {
        $this->refreshFormData([
            'title',
        ]);
    }
}
