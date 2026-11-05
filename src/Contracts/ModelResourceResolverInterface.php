<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Contracts;

interface ModelResourceResolverInterface
{
    /**
     * @param  class-string  $modelClass
     * @return ?class-string
     */
    public function resourceFor(string $modelClass): ?string;
}
