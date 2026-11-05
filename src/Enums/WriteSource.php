<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

enum WriteSource: string
{
    case User = 'user';
    case Import = 'import';
    case Migration = 'migration';
    case Ai = 'ai_inferred';
}
