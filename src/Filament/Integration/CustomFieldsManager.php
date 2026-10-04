<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration;

use Relaticle\CustomFields\Filament\Integration\Builders\ExporterBuilder;
use Relaticle\CustomFields\Filament\Integration\Builders\FormBuilder;
use Relaticle\CustomFields\Filament\Integration\Builders\ImporterBuilder;
use Relaticle\CustomFields\Filament\Integration\Builders\InfolistBuilder;
use Relaticle\CustomFields\Filament\Integration\Builders\TableBuilder;

final class CustomFieldsManager
{
    public function table(): TableBuilder
    {
        return app(TableBuilder::class);
    }

    public function form(): FormBuilder
    {
        return new FormBuilder;
    }

    public function infolist(): InfolistBuilder
    {
        return app(InfolistBuilder::class);
    }

    public function importer(): ImporterBuilder
    {
        return new ImporterBuilder;
    }

    public function exporter(): ExporterBuilder
    {
        return app(ExporterBuilder::class);
    }
}
