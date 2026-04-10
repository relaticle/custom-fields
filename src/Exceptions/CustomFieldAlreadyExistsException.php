<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Exceptions;

use Exception;

class CustomFieldAlreadyExistsException extends Exception
{
    public static function whenAdding(string $code): self
    {
        throw new self(sprintf('Could not create custom field `%s` because it already exists', $code));
    }
}
