<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Observers;

use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use RuntimeException;

class CustomFieldObserver
{
    /**
     * Prevent modification of protected attributes on system-defined fields.
     */
    public function updating(CustomField $customField): void
    {
        if (! $customField->getOriginal('system_defined')) {
            return;
        }

        if ($customField->isDirty(['name', 'code', 'type'])) {
            throw new RuntimeException('Cannot modify name, code, or type of system-defined fields.');
        }
    }

    /**
     * Clear field cache after create or update.
     */
    public function saved(CustomField $customField): void
    {
        BackendVisibilityService::clearCache($customField->entity_type);
    }

    public function deleted(CustomField $customField): void
    {
        BackendVisibilityService::clearCache($customField->entity_type);

        // Delete the custom field options
        $customField->options()->delete();

        // Delete the custom field values
        $customField->values()->delete();
    }
}
