<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Resources\Comments;

use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use UnitEnum;

/**
 * The Post fixture renders its view page from the form schema, which is what a resource
 * without an infolist falls back to. This one defines an infolist so the infolist path
 * has a page to be tested through.
 */
final class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|UnitEnum|null $navigationGroup = 'Blog';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeft;

    protected static ?string $recordTitleAttribute = 'body';

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('body'),

                CustomFields::infolist()->build(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('body'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComments::route('/'),
            'view' => Pages\ViewComment::route('/{record}'),
        ];
    }
}
