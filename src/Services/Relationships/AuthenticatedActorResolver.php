<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Relationships;

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Contracts\LinkActorResolverInterface;

final readonly class AuthenticatedActorResolver implements LinkActorResolverInterface
{
    public function resolve(): ?Model
    {
        $user = auth()->user();

        return $user instanceof Model ? $user : null;
    }
}
