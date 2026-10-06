<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Answers who is creating a relationship link. Hosts rebind it so writes made by an agent,
 * an API token, or an import are stamped as that actor instead of the signed-in user.
 */
interface LinkActorResolverInterface
{
    public function resolve(): ?Model;
}
