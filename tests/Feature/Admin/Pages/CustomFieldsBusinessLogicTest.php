<?php

declare(strict_types=1);

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

describe('CustomFieldsPage - Business VisibilityLogic and Integration', function (): void {
    it('assigns correct entity type when creating sections', function (): void {
        // Arrange
        $sectionData = [
            'name' => 'Post Section',
            'code' => 'post_section',
        ];

        // Act
        livewire(CustomFieldsPage::class)
            ->call('setCurrentEntityType', $this->postEntityType)
            ->callAction('createSection', $sectionData);

        // Assert
        $this->assertDatabaseHas(CustomFieldSection::class, [
            'entity_type' => $this->postEntityType,
            'name' => $sectionData['name'],
            'code' => $sectionData['code'],
        ]);
    });

    it('filters sections by entity type correctly', function (): void {
        // Arrange
        $userSection = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();
        $postSection = CustomFieldSection::factory()
            ->forEntityType($this->postEntityType)
            ->create();

        // Act
        $component = livewire(CustomFieldsPage::class)
            ->call('setCurrentEntityType', $this->userEntityType);

        // Assert
        $component->assertSee($userSection->name)
            ->assertDontSee($postSection->name);
    });

});
