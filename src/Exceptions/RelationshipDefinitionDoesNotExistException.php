<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Exceptions;

use Exception;

final class RelationshipDefinitionDoesNotExistException extends Exception
{
    public static function forField(string $code): self
    {
        return new self(sprintf('Record field `%s` has no relationship definition. Create one with CreateRelationshipDefinition, or run `php artisan migrate` to convert a 3.x record field.', $code));
    }

    public static function whenLinking(int|string $key): self
    {
        return new self(sprintf('Could not write links for relationship `%s` because it no longer exists', $key));
    }
}
