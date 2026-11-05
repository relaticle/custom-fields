<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Models;

use Relaticle\CustomFields\Models\CustomFieldValue;

class CountingCustomFieldValue extends CustomFieldValue
{
    public static int $resolutions = 0;

    public function getValue(): mixed
    {
        self::$resolutions++;

        return parent::getValue();
    }
}
