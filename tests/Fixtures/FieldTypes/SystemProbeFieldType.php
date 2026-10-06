<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\FieldTypes;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;

class SystemProbeFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('system-probe')
            ->label('System probe')
            ->icon('heroicon-o-lock-closed')
            ->systemOnly();
    }
}
