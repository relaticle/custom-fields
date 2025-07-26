<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration;

use Relaticle\CustomFields\Filament\Integration\Builders\ExporterBuilder;
use Relaticle\CustomFields\Filament\Integration\Builders\FormBuilder;
use Relaticle\CustomFields\Filament\Integration\Builders\InfolistBuilder;
use Relaticle\CustomFields\Filament\Integration\Builders\TableBuilder;

final class CustomFieldsManager
{
    public function table(): TableBuilder
    {
        return new TableBuilder;
    }

    public function form(): FormBuilder
    {
        return new FormBuilder;
    }

    public function infolist(): InfolistBuilder
    {
        return new InfolistBuilder;
    }

    public function exporter(): ExporterBuilder
    {
        return new ExporterBuilder;
    }
}
