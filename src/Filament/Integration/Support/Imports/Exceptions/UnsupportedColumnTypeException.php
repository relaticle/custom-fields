<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Support\Imports\Exceptions;

use Exception;

/**
 * Exception thrown when an unsupported column type is encountered.
 */
final class UnsupportedColumnTypeException extends Exception
{
    /**
     * Constructor for unsupported column type.
     *
     * @param  string  $columnType  The unsupported column type
     */
    public function __construct(string $columnType)
    {
        parent::__construct('Unsupported custom field column type: '.$columnType);
    }
}
