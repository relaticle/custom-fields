<?php

declare(strict_types=1);

use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\Filament\Management\Pages\CustomFieldsManagementPage as CustomFieldsPage;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    // Arrange: Create authenticated user for all tests
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Set up common test entity types for all tests
    $this->postEntityType = Post::class;
    $this->userEntityType = User::class;
});

describe('CustomFieldsPage - Essential User Interactions', function (): void {
    it('can access page and interact with entity type selection', function (): void {
        // Arrange
        $section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();

        // Act & Assert - real user workflow: access page, select entity type, see content
        livewire(CustomFieldsPage::class)
            ->assertSuccessful()
            ->call('setCurrentEntityType', $this->userEntityType)
            ->assertSee($section->name);
    });

    // A phone cannot use a 200px entity rail beside the table, and the browser re-check of the
    // stacked layout happens in the host panel.
    it('stacks the entity rail above the fields on a narrow viewport', function (): void {
        config(['custom-fields.features' => FeatureConfigurator::configure()
            ->disable(CustomFieldsFeature::SYSTEM_SECTIONS)]);

        livewire(CustomFieldsPage::class)
            ->assertSuccessful()
            ->assertSeeHtml('flex flex-col gap-6 md:flex-row')
            ->assertSeeHtml('class="md:hidden"')
            ->assertSeeHtml('hidden shrink-0 md:block md:min-w-48')
            ->assertSeeHtml('fi-tabs fi-vertical')
            ->assertSee('Posts');
    });
});
