<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Imports;

use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

/**
 * A host application's importer, wired the way the documentation tells people to wire
 * one: their own columns, plus every custom field the entity has.
 */
final class PostImporter extends Importer
{
    protected static ?string $model = Post::class;

    /**
     * @return array<ImportColumn>
     */
    public static function getColumns(): array
    {
        return [
            ImportColumn::make('title')
                ->requiredMapping()
                ->rules(['required', 'string']),
            ...CustomFields::importer()->forModel(new Post)->columns()->all(),
        ];
    }

    public function resolveRecord(): ?Model
    {
        $post = new Post;
        $post->author_id = $this->import->user_id;

        return $post;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        return 'done';
    }

    protected function afterSave(): void
    {
        CustomFields::importer()->forModel($this->record)->saveValues();
    }
}
