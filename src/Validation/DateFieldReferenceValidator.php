<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Validation;

final class DateFieldReferenceValidator
{
    /**
     * Detect if a field's reference chain creates a cycle.
     *
     * @param  string  $fieldCode  The field being saved
     * @param  array<string, string>  $fieldsWithReferences  Map of field_code => referenced_field_code
     */
    public static function hasCycle(string $fieldCode, array $fieldsWithReferences): bool
    {
        $visited = [];
        $current = $fieldCode;

        while (isset($fieldsWithReferences[$current])) {
            if (isset($visited[$current])) {
                return true;
            }

            $visited[$current] = true;
            $current = $fieldsWithReferences[$current];
        }

        return false;
    }
}
