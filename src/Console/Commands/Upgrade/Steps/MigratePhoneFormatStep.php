<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

/**
 * Migrates phone field values from single string format (v2) to array format (v3).
 */
final class MigratePhoneFormatStep extends MigrateStringToJsonFormatStep
{
    protected function fieldType(): string
    {
        return 'phone';
    }

    protected function fieldTypeLabel(): string
    {
        return 'Phone';
    }
}
