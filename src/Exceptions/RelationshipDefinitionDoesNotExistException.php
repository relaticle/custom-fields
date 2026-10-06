<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Exceptions;

use Exception;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;

final class RelationshipDefinitionDoesNotExistException extends Exception
{
    /**
     * The remedy differs: with the feature off no definition was ever created and the upgrade
     * command is a no-op, so the flag has to come first.
     */
    public static function forField(string $code): self
    {
        if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_RELATIONSHIPS)) {
            return new self(sprintf('Record field `%s` has no relationship definition, and the relationships feature is off. Enable it, migrate, then run `php artisan custom-fields:upgrade`.', $code));
        }

        return new self(sprintf('Record field `%s` has no relationship definition. Run `php artisan custom-fields:upgrade` to give every record field one.', $code));
    }

    public static function whenLinking(int|string $key): self
    {
        return new self(sprintf('Could not write links for relationship `%s` because it no longer exists', $key));
    }
}
