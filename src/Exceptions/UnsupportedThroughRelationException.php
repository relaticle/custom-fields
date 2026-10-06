<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Exceptions;

use Exception;

final class UnsupportedThroughRelationException extends Exception
{
    public static function missing(string $model, string $relation): self
    {
        return new self(sprintf(
            'Model `%s` has no relation named `%s`, so custom fields cannot be read through it.',
            $model,
            $relation,
        ));
    }

    public static function toMany(string $model, string $relation, string $type): self
    {
        return new self(sprintf(
            'Relation `%s` on `%s` is a %s. Custom fields are read through to-one relations only (BelongsTo, HasOne, MorphOne), because a to-many relation offers no single value to sort by.',
            $relation,
            $model,
            class_basename($type),
        ));
    }

    public static function polymorphicTarget(string $model, string $relation): self
    {
        return new self(sprintf(
            'Relation `%s` on `%s` is a MorphTo, so its target model varies per row and no single set of custom fields describes it. Point `through()` at a relation with one target model.',
            $relation,
            $model,
        ));
    }

    public static function withoutCustomFields(string $model, string $relation, string $related): self
    {
        return new self(sprintf(
            'Relation `%s` on `%s` resolves to `%s`, which does not implement HasCustomFields.',
            $relation,
            $model,
            $related,
        ));
    }
}
