<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Enums;

// The only surfaces that fork: each one is markup Filament has no primitive for.
enum UiSurface: string
{
    case RelationshipConfigurator = 'relationship-configurator';

    case RecordChips = 'record-chips';

    case RecordPicker = 'record-picker';

    case TypePicker = 'type-picker';

    case AttributeTable = 'attribute-table';
}
