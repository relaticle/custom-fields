<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages;

use Filament\Resources\Pages\CreateRecord;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\PostResource;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;
}
