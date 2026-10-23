<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\FieldTypes;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;

class SystemFirstFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('system-first')
            ->label('System first')
            ->icon('heroicon-o-lock-closed')
            ->priority(1)
            ->systemOnly();
    }
}
