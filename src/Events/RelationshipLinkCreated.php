<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Events;

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Data\WriteContext;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;

final readonly class RelationshipLinkCreated
{
    /**
     * @param  Model|null  $record  the record whose write made the change, null for a change to the definition itself
     */
    public function __construct(
        public CustomFieldLink $link,
        public CustomFieldRelationship $definition,
        public ?Model $record,
        public WriteContext $context,
    ) {}
}
