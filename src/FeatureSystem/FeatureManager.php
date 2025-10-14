<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FeatureSystem;

use Relaticle\CustomFields\Enums\CustomFieldsFeature;

/**
 * Simple service for runtime feature checking
 */
class FeatureManager
{
    /**
     * Check if a specific feature is enabled
     */
    public static function isEnabled(CustomFieldsFeature $feature): bool
    {
        $config = config('custom-fields.features');

        return $config->isEnabled($feature);
    }
}
