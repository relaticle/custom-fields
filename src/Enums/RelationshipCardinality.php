<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

use Filament\Support\Contracts\HasLabel;

enum RelationshipCardinality: string implements HasLabel
{
    case OneToOne = 'one_to_one';
    case OneToMany = 'one_to_many';
    case ManyToOne = 'many_to_one';
    case ManyToMany = 'many_to_many';

    // Cardinality reads from the from side to the to side, so one_to_many means one from-record
    // holds many links: the constrained end is the to side, not the from side.
    public function fromSideIsSingle(): bool
    {
        return in_array($this, [self::OneToOne, self::ManyToOne], true);
    }

    public function toSideIsSingle(): bool
    {
        return in_array($this, [self::OneToOne, self::OneToMany], true);
    }

    /**
     * The same relationship read from the other end: one_to_many holds as many records from
     * the to side as many_to_one holds from the from side. The two even ones are their own
     * inverse, so the transform is an involution.
     */
    public function inverse(): self
    {
        return match ($this) {
            self::OneToMany => self::ManyToOne,
            self::ManyToOne => self::OneToMany,
            self::OneToOne, self::ManyToMany => $this,
        };
    }

    /**
     * The same relationship with the from record holding one target or many, leaving the to
     * side where it is. A face that asks only how many records its own field holds answers
     * for one end, so the other end's constraint has to survive the answer.
     */
    public function fromSideHolds(bool $many): self
    {
        return match (true) {
            $many && $this->toSideIsSingle() => self::OneToMany,
            $many => self::ManyToMany,
            $this->toSideIsSingle() => self::OneToOne,
            default => self::ManyToOne,
        };
    }

    /**
     * Whether moving to the given cardinality takes an end from many records to one, which
     * closes the edges that no longer fit.
     */
    public function narrows(self $to): bool
    {
        if (! $this->fromSideIsSingle() && $to->fromSideIsSingle()) {
            return true;
        }

        return ! $this->toSideIsSingle() && $to->toSideIsSingle();
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::OneToOne => __('custom-fields::custom-fields.enums.relationship_cardinality.one_to_one'),
            self::OneToMany => __('custom-fields::custom-fields.enums.relationship_cardinality.one_to_many'),
            self::ManyToOne => __('custom-fields::custom-fields.enums.relationship_cardinality.many_to_one'),
            self::ManyToMany => __('custom-fields::custom-fields.enums.relationship_cardinality.many_to_many'),
        };
    }
}
