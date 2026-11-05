<?php

declare(strict_types=1);

use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Tests\Fixtures\Livewire\ThroughTable;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

afterEach(function (): void {
    ThroughTable::$configureUsing = null;
    Model::preventLazyLoading(false);
});

function commentsOnPostsWith(CustomField $field, int $count): void
{
    for ($index = 0; $index < $count; $index++) {
        $post = Post::factory()->create();
        $post->saveCustomFieldValue($field, 'Value '.$index);

        Comment::factory()->create(['post_id' => $post->getKey()]);
    }
}

function valueQueryCount(int $rows, CustomField $field): int
{
    Comment::query()->delete();
    Post::query()->forceDelete();

    commentsOnPostsWith($field, $rows);

    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        throughTable(
            Comment::class,
            Post::class,
            'post',
            fn (Table $table): Table => $table->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with('post.customFieldValues.customField')
            ),
        )->assertCountTableRecords($rows);

        return count(array_filter(
            DB::getQueryLog(),
            static fn (array $entry): bool => str_contains($entry['query'], 'custom_field_values'),
        ));
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }
}

it('reads the related fields in a fixed number of queries when the host eager loads', function (): void {
    $field = throughTextField(Post::class, 'category', 'Category');

    $forTwoRows = valueQueryCount(2, $field);
    $forSixRows = valueQueryCount(6, $field);

    expect($forSixRows)->toBe($forTwoRows);
});

it('raises the lazy load violation, not a silent n+1, when the host does not eager load', function (): void {
    $field = throughTextField(Post::class, 'category', 'Category');

    commentsOnPostsWith($field, 3);

    Model::preventLazyLoading();

    $thrown = null;

    try {
        throughTable(Comment::class, Post::class, 'post')->assertCountTableRecords(3);
    } catch (Throwable $throwable) {
        $thrown = $throwable;
    } finally {
        Model::preventLazyLoading(false);
    }

    while ($thrown?->getPrevious() instanceof Throwable) {
        $thrown = $thrown->getPrevious();
    }

    expect($thrown)->toBeInstanceOf(LazyLoadingViolationException::class)
        ->and($thrown->getMessage())->toContain('[post]')
        ->and($thrown->getMessage())->toContain(Comment::class);
});
