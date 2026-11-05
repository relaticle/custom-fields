<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

enum LinkSide: string
{
    case From = 'from';
    case To = 'to';

    public function opposite(): self
    {
        return match ($this) {
            self::From => self::To,
            self::To => self::From,
        };
    }

    public function typeColumn(): string
    {
        return $this->value.'_entity_type';
    }

    public function idColumn(): string
    {
        return $this->value.'_entity_id';
    }

    public function linksRelation(): string
    {
        return match ($this) {
            self::From => 'outgoingLinks',
            self::To => 'incomingLinks',
        };
    }
}
