<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\WriteSource;
use Spatie\LaravelData\Data;

final class WriteContext extends Data
{
    public function __construct(
        public readonly WriteSource $source,
        public readonly ?Model $actor,
        public readonly ?float $confidence = null,
    ) {}
}
