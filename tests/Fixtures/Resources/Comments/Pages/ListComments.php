<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Resources\Comments\Pages;

use Filament\Resources\Pages\ListRecords;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Comments\CommentResource;

final class ListComments extends ListRecords
{
    protected static string $resource = CommentResource::class;
}
