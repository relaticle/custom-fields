<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

/**
 * A comment holds no custom fields of its own; the table shows the post's, through the
 * relation the rows already have.
 */
final class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('post.customFieldValues.customField'))
            ->columns([
                TextColumn::make('body'),

                ...CustomFields::table()->forModel(Post::class)->through('post')->columns(),
            ])
            ->filters([
                ...CustomFields::table()->forModel(Post::class)->through('post')->filters(),
            ]);
    }
}
