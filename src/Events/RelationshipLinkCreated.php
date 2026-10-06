<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Events;

use Relaticle\CustomFields\Models\CustomFieldLink;

final readonly class RelationshipLinkCreated
{
    public function __construct(public CustomFieldLink $link) {}
}
