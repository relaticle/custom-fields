<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Console\Commands\Upgrade\Steps;

/**
 * Migrates email field values from single string format (v2) to array format (v3).
 */
final class MigrateEmailFormatStep extends MigrateStringToJsonFormatStep
{
    protected function fieldType(): string
    {
        return 'email';
    }

    protected function fieldTypeLabel(): string
    {
        return 'Email';
    }
}
