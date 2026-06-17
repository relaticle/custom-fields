<?php

declare(strict_types=1);

use Filament\Forms\Components\Toggle;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\FeatureSystem\FeatureConfigurator;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Pages\CustomFieldsManagementPage as CustomFieldsPage;
use Relaticle\CustomFields\Filament\Management\Schemas\SectionForm;
use Relaticle\CustomFields\Livewire\ManageCustomFieldSection;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Visibility\CoreVisibilityLogicService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Comment;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\Tag;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    // Skip if sections are disabled (these tests require sections)
    if (! FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_SECTIONS)) {
        $this->markTestSkipped('Sections feature must be enabled for these tests.');
    }

    // Arrange: Create authenticated user for all tests
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Set up common test entity types for all tests
    $this->postEntityType = Post::class;
    $this->userEntityType = User::class;
});

describe('CustomFieldsPage - Section Management', function (): void {
    it('can create a new section with valid data', function (): void {
        // Arrange
        $sectionData = [
            'name' => 'Test Section',
            'code' => 'test_section',
        ];

        // Act
        $livewireTest = livewire(CustomFieldsPage::class)
            ->call('setCurrentEntityType', $this->userEntityType)
            ->callAction('createSection', $sectionData);

        // Assert
        $livewireTest->assertHasNoFormErrors()->assertNotified();

        $this->assertDatabaseHas(CustomFieldSection::class, [
            'name' => $sectionData['name'],
            'code' => $sectionData['code'],
            'entity_type' => $this->userEntityType,
        ]);
    });

    it('validates section form fields', function (string $field, mixed $value): void {
        livewire(CustomFieldsPage::class)
            ->call('setCurrentEntityType', $this->userEntityType)
            ->callAction('createSection', [$field => $value])
            ->assertHasFormErrors([$field]);
    })->with([
        'name is required' => ['name', ''],
        'code is required' => ['code', ''],
    ]);

    it('validates section code must be unique', function (): void {
        // Arrange
        $existingSection = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create(['code' => 'unique_test_code']);

        // Act
        $response = livewire(CustomFieldsPage::class)
            ->call('setCurrentEntityType', $this->userEntityType)
            ->callAction('createSection', [
                'name' => 'Test Section',
                'code' => 'unique_test_code', // Same code as existing section
            ]);

        // Assert - either validation error OR no new section created
        try {
            $response->assertHasFormErrors(['code']);
        } catch (Exception) {
            // Alternative check: ensure no duplicate was created
            $sectionsWithSameCode = CustomFieldSection::where('code', 'unique_test_code')
                ->where('entity_type', $this->userEntityType)
                ->count();
            expect($sectionsWithSameCode)->toBe(1, 'Should not create duplicate sections with same code');
        }
    });

    it('can update sections order', function (): void {
        // Arrange
        $section1 = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create(['sort_order' => 0]);
        $section2 = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create(['sort_order' => 1]);

        // Act
        livewire(CustomFieldsPage::class)
            ->call('setCurrentEntityType', $this->userEntityType)
            ->call('updateSectionsOrder', [$section2->getKey(), $section1->getKey()]);

        // Assert
        $this->assertDatabaseHas(CustomFieldSection::class, [
            'id' => $section2->getKey(),
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas(CustomFieldSection::class, [
            'id' => $section1->getKey(),
            'sort_order' => 1,
        ]);
    });

    it('removes deleted sections from view', function (): void {
        // Arrange
        $section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();

        $component = livewire(CustomFieldsPage::class)
            ->call('setCurrentEntityType', $this->userEntityType)
            ->assertSee($section->name);

        // Act - simulate section deletion
        $section->delete();
        $component->call('sectionDeleted');

        // Assert
        $component->assertDontSee($section->name);
    });
});

describe('ManageCustomFieldSection - Section Actions', function (): void {
    beforeEach(function (): void {
        $this->section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create();
    });

    it('can edit a section with valid data', function (): void {
        // Arrange
        $newData = [
            'name' => 'Updated Section Name',
            'code' => 'updated_code',
        ];

        // Act
        $livewireTest = livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->callAction('edit', $newData);

        // Assert
        $livewireTest->assertHasNoFormErrors();

        $this->assertDatabaseHas(CustomFieldSection::class, [
            'id' => $this->section->getKey(),
            'name' => $newData['name'],
            'code' => $newData['code'],
        ]);
    });

    it('can activate an inactive section', function (): void {
        // Arrange
        $inactiveSection = CustomFieldSection::factory()
            ->inactive()
            ->forEntityType($this->userEntityType)
            ->create();

        // Act
        livewire(ManageCustomFieldSection::class, [
            'section' => $inactiveSection,
            'entityType' => $this->userEntityType,
        ])->callAction('activate');

        // Assert
        $this->assertDatabaseHas(CustomFieldSection::class, [
            'id' => $inactiveSection->getKey(),
            'active' => true,
        ]);
    });

    it('can deactivate an active section', function (): void {
        // Act
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->callAction('deactivate');

        // Assert
        $this->assertDatabaseHas(CustomFieldSection::class, [
            'id' => $this->section->getKey(),
            'active' => false,
        ]);
    });

    it('can delete an inactive non-system section', function (): void {
        // Arrange
        $deletableSection = CustomFieldSection::factory()
            ->inactive()
            ->forEntityType($this->userEntityType)
            ->create();

        // Act
        livewire(ManageCustomFieldSection::class, [
            'section' => $deletableSection,
            'entityType' => $this->userEntityType,
        ])->callAction('delete');

        // Assert
        $this->assertDatabaseMissing(CustomFieldSection::class, [
            'id' => $deletableSection->getKey(),
        ]);
    });

    it('cannot delete an active section', function (): void {
        livewire(ManageCustomFieldSection::class, [
            'section' => $this->section,
            'entityType' => $this->userEntityType,
        ])->assertActionHidden('delete');
    });

    it('cannot delete a system-defined section', function (): void {
        // Arrange
        $systemSection = CustomFieldSection::factory()
            ->inactive()
            ->systemDefined()
            ->forEntityType($this->userEntityType)
            ->create();

        // Act & Assert - delete and deactivate actions are hidden for system-defined sections
        livewire(ManageCustomFieldSection::class, [
            'section' => $systemSection,
            'entityType' => $this->userEntityType,
        ])->assertActionHidden('delete')
            ->assertActionHidden('deactivate');
    });

    it('cannot delete a section containing system-defined fields', function (): void {
        // Arrange
        $section = CustomFieldSection::factory()
            ->inactive()
            ->forEntityType($this->userEntityType)
            ->create();

        // Create a system-defined field in the section
        CustomField::factory()->create([
            'custom_field_section_id' => $section->getKey(),
            'entity_type' => $this->userEntityType,
            'active' => false,
            'system_defined' => true,
            'type' => 'text',
        ]);

        // Act & Assert
        livewire(ManageCustomFieldSection::class, [
            'section' => $section,
            'entityType' => $this->userEntityType,
        ])->assertActionVisible('delete')
            ->assertActionDisabled('delete');
    });

    it('can delete a section with only user-defined fields', function (): void {
        // Arrange
        $section = CustomFieldSection::factory()
            ->inactive()
            ->forEntityType($this->userEntityType)
            ->create();

        // Create a user-defined field in the section
        CustomField::factory()->create([
            'custom_field_section_id' => $section->getKey(),
            'entity_type' => $this->userEntityType,
            'active' => false,
            'system_defined' => false,
            'type' => 'text',
        ]);

        // Act & Assert
        livewire(ManageCustomFieldSection::class, [
            'section' => $section,
            'entityType' => $this->userEntityType,
        ])->assertActionVisible('delete')
            ->assertActionEnabled('delete');
    });
});

describe('ManageCustomFieldSection - condition value persistence', function (): void {
    it('keeps a saved condition value when only the operator changes on edit', function (): void {
        config()->set('custom-fields.features', FeatureConfigurator::configure()
            ->enable(
                CustomFieldsFeature::SYSTEM_SECTIONS,
                CustomFieldsFeature::SYSTEM_MANAGEMENT_INTERFACE,
                CustomFieldsFeature::FIELD_CONDITIONAL_VISIBILITY,
                CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY,
            )
        );

        $this->setEntityConditionRelations([
            Comment::class => ['post.tagModels' => 'Post → Tags'],
        ]);

        $tag = Tag::factory()->create();

        $section = CustomFieldSection::factory()
            ->forEntityType(Comment::class)
            ->create([
                'name' => 'Conditional Section',
                'code' => 'conditional_section',
                'settings' => [
                    'visibility' => [
                        'mode' => VisibilityMode::SHOW_WHEN,
                        'logic' => VisibilityLogic::ALL,
                        'conditions' => [
                            [
                                'field_code' => 'post.tagModels',
                                'operator' => VisibilityOperator::IS_IN,
                                'value' => [$tag->id],
                                'source' => ConditionSource::RelationAttribute,
                            ],
                        ],
                    ],
                ],
            ]);

        $statePath = 'mountedActions.0.data';

        $component = livewire(ManageCustomFieldSection::class, [
            'section' => $section,
            'entityType' => Comment::class,
        ])->mountAction('edit');

        $conditionKey = array_key_first($component->get($statePath.'.settings.visibility.conditions'));

        $component
            ->set(sprintf('%s.settings.visibility.conditions.%s.operator', $statePath, $conditionKey), VisibilityOperator::IS_NOT_IN->value)
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $condition = app(CoreVisibilityLogicService::class)
            ->getVisibilityDataFromSection($section->refresh())
            ->conditions
            ->first();

        expect($condition->operator)->toBe(VisibilityOperator::IS_NOT_IN)
            ->and($condition->value)->toBe([$tag->id]);
    });
});

describe('SectionForm - extendSchemaUsing', function (): void {
    afterEach(function (): void {
        SectionForm::flushSchemaExtensions();
    });

    it('passes the section entity type to the extension callback', function (): void {
        $captured = null;

        SectionForm::extendSchemaUsing(function (array $schema, string $entityType) use (&$captured): array {
            $captured = $entityType;

            return $schema;
        });

        SectionForm::entityType($this->userEntityType)->schema();

        expect($captured)->toBe($this->userEntityType);
    });

    it('persists a field added via extendSchemaUsing into the settings extra bag on create', function (): void {
        SectionForm::extendSchemaUsing(fn (array $schema): array => [
            ...$schema,
            Toggle::make('settings.extra.render_as_tab')->label('Render as own tab'),
        ]);

        livewire(CustomFieldsPage::class)
            ->call('setCurrentEntityType', $this->userEntityType)
            ->callAction('createSection', [
                'name' => 'Tabbed Section',
                'code' => 'tabbed_section',
                'settings' => ['extra' => ['render_as_tab' => true]],
            ])
            ->assertHasNoFormErrors();

        $section = CustomFieldSection::query()
            ->where('code', 'tabbed_section')
            ->where('entity_type', $this->userEntityType)
            ->sole();

        expect($section->settings->extra)->toBe(['render_as_tab' => true]);
    });

    it('keeps the extra bag value when the section is edited', function (): void {
        $section = CustomFieldSection::factory()
            ->forEntityType($this->userEntityType)
            ->create([
                'code' => 'tabbed_section',
                'settings' => ['extra' => ['render_as_tab' => true]],
            ]);

        SectionForm::extendSchemaUsing(fn (array $schema): array => [
            ...$schema,
            Toggle::make('settings.extra.render_as_tab')->label('Render as own tab'),
        ]);

        livewire(ManageCustomFieldSection::class, [
            'section' => $section,
            'entityType' => $this->userEntityType,
        ])
            ->callAction('edit', [
                'name' => 'Renamed Section',
                'code' => $section->code,
                'settings' => ['extra' => ['render_as_tab' => true]],
            ])
            ->assertHasNoFormErrors();

        expect($section->refresh()->settings->extra)->toBe(['render_as_tab' => true]);
    });
});
