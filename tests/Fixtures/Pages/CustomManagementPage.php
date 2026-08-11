<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Fixtures\Pages;

use Filament\Panel;
use Relaticle\CustomFields\Filament\Management\Pages\CustomFieldsManagementPage;

class CustomManagementPage extends CustomFieldsManagementPage
{
    public static function getSlug(?Panel $panel = null): string
    {
        return 'settings/custom-fields';
    }
}
