<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Resources\Comments\Pages;

use Filament\Resources\Pages\ViewRecord;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Comments\CommentResource;

final class ViewComment extends ViewRecord
{
    protected static string $resource = CommentResource::class;
}
