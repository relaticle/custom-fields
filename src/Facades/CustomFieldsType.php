<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use Relaticle\CustomFields\Collections\FieldTypeCollection;
use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\FieldTypeSystem\FieldManager;

/**
 * @method static FieldTypeCollection toCollection()
 *
 * @see FieldManager
 */
final class CustomFieldsType extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FieldManager::class;
    }

    /**
     * @param  array<int|string, class-string<FieldTypeDefinitionInterface>> | Closure  $fieldTypes
     */
    public static function register(array|Closure $fieldTypes): void
    {
        self::resolved(function (FieldManager $fieldTypeManager) use ($fieldTypes): void {
            $fieldTypeManager->register($fieldTypes);
        });
    }
}
