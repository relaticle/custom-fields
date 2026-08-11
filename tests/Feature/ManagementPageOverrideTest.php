<?php

declare(strict_types=1);

use Filament\Panel;
use Relaticle\CustomFields\CustomFieldsPlugin;
use Relaticle\CustomFields\Filament\Management\Pages\CustomFieldsManagementPage;
use Relaticle\CustomFields\Tests\Fixtures\Pages\CustomManagementPage;

it('registers the packaged management page by default', function (): void {
    expect(CustomFieldsPlugin::make()->getManagementPage())
        ->toBe(CustomFieldsManagementPage::class);
});

it('registers an overridden management page', function (): void {
    expect(CustomFieldsPlugin::make()->managementPage(CustomManagementPage::class)->getManagementPage())
        ->toBe(CustomManagementPage::class);
});

it('rejects a management page that does not extend the packaged one', function (): void {
    CustomFieldsPlugin::make()->managementPage(Panel::class);
})->throws(InvalidArgumentException::class, 'must extend');

it('puts only the overridden page on the panel', function (): void {
    $panel = Panel::make();

    CustomFieldsPlugin::make()->managementPage(CustomManagementPage::class)->register($panel);

    expect($panel->getPages())
        ->toContain(CustomManagementPage::class)
        ->not->toContain(CustomFieldsManagementPage::class);
});

it('puts the packaged page on the panel when not overridden', function (): void {
    $panel = Panel::make();

    CustomFieldsPlugin::make()->register($panel);

    expect($panel->getPages())->toContain(CustomFieldsManagementPage::class);
});
