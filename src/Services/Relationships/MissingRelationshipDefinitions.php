<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Services\Relationships;

use Relaticle\CustomFields\Exceptions\RelationshipDefinitionDoesNotExistException;
use Relaticle\CustomFields\Models\CustomField;

/**
 * A record field that cannot resolve its definition renders nothing rather than taking the
 * page down with it, so the log is the only place the problem shows. Once per field per
 * request: a table asks its columns for every row.
 */
final class MissingRelationshipDefinitions
{
    /** @var array<string, true> */
    private array $reported = [];

    public function report(CustomField $field): void
    {
        $key = (string) $field->getKey();

        if (isset($this->reported[$key])) {
            return;
        }

        $this->reported[$key] = true;

        report(RelationshipDefinitionDoesNotExistException::forField($field->code));
    }
}
