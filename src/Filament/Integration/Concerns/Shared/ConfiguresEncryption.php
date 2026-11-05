<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Shared;

use Relaticle\CustomFields\Models\CustomField;

/**
 * ABOUTME: Trait providing encryption handling for components.
 * ABOUTME: Standardizes how components check and handle encrypted fields.
 *
 * @internal
 */
trait ConfiguresEncryption
{
    /**
     * Check if a field is encrypted.
     */
    protected function isEncrypted(CustomField $customField): bool
    {
        return $customField->settings->encrypted ?? false;
    }

    /**
     * Check if a field is not encrypted.
     */
    protected function isNotEncrypted(CustomField $customField): bool
    {
        return ! $this->isEncrypted($customField);
    }
}
