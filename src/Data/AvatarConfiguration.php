<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Relaticle\CustomFields\Enums\AvatarShape;
use Spatie\LaravelData\Data;

final class AvatarConfiguration extends Data
{
    public function __construct(
        public ?string $attribute = null,
        public AvatarShape $shape = AvatarShape::Circle,
    ) {}

    public function hasAttribute(): bool
    {
        return $this->attribute !== null;
    }

    public function getCssClass(): string
    {
        return $this->shape->getCssClass();
    }

    /**
     * Recreate object from var_export() for Laravel config:cache
     */
    public static function __set_state(array $properties): self
    {
        $shape = $properties['shape'] ?? AvatarShape::Circle;

        if (is_string($shape)) {
            $shape = AvatarShape::from($shape);
        }

        return new self(
            attribute: $properties['attribute'] ?? null,
            shape: $shape,
        );
    }
}
