<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;
use RuntimeException;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\ActionGroup::make([
                Actions\DeleteAction::make(),
            ]),
            Actions\Action::make('randomize_title')
                ->databaseTransaction()
                ->action(action: function (Post $record): void {
                    DB::afterCommit(function (): void {
                        throw new RuntimeException('This exception, happening after the successful commit of the current transaction, should not trigger a rollback by Filament.');
                    });

                    $record->title = 'Test';
                    $record->save();
                }),
        ];
    }

    public function refreshTitle(): void
    {
        $this->refreshFormData([
            'title',
        ]);
    }
}
