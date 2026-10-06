<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Observers;

use Relaticle\CustomFields\Models\CustomFieldSection;

final class CustomFieldSectionObserver
{
    public function deleted(CustomFieldSection $customFieldSection): void
    {
        $customFieldSection->fields()->withDeactivated()->delete();
    }
}
