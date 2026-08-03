<?php

declare(strict_types=1);

use Relaticle\CustomFields\Facades\CustomFields;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportDataStorage;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    $this->section = CustomFieldSection::factory()
        ->forEntityType(Post::class)
        ->create();

    $this->referralSource = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'referral_source',
        'type' => 'text',
    ]);

    $this->caseworker = CustomField::factory()->create([
        'custom_field_section_id' => $this->section->getKey(),
        'entity_type' => Post::class,
        'code' => 'caseworker',
        'type' => 'text',
    ]);
});

it('saves the custom field values the imported row carried', function (): void {
    $post = Post::factory()->create();

    ImportDataStorage::setMultiple($post, [
        'referral_source' => 'Continuum of Care',
        'caseworker' => 'Alex Rivera',
    ]);

    CustomFields::importer()->forModel($post)->saveValues();

    $post->load('customFieldValues');

    expect($post->getCustomFieldValue($this->referralSource))->toBe('Continuum of Care')
        ->and($post->getCustomFieldValue($this->caseworker))->toBe('Alex Rivera');
});

it('leaves custom fields the row did not carry untouched', function (): void {
    $post = Post::factory()->create();

    $post->saveCustomFieldValue($this->caseworker, 'Alex Rivera');

    ImportDataStorage::set($post, 'referral_source', 'Street Outreach');

    CustomFields::importer()->forModel($post)->saveValues();

    $post->load('customFieldValues');

    expect($post->getCustomFieldValue($this->referralSource))->toBe('Street Outreach')
        ->and($post->getCustomFieldValue($this->caseworker))->toBe('Alex Rivera');
});

it('does nothing when the row carried no custom field columns', function (): void {
    $post = Post::factory()->create();

    $post->saveCustomFieldValue($this->caseworker, 'Alex Rivera');

    CustomFields::importer()->forModel($post)->saveValues();

    $post->load('customFieldValues');

    expect($post->getCustomFieldValue($this->caseworker))->toBe('Alex Rivera');
});

it('clears a value when the row carried the column but left it blank', function (): void {
    $post = Post::factory()->create();

    $post->saveCustomFieldValue($this->referralSource, 'Continuum of Care');

    // A mapped-but-blank CSV cell reaches storage as null, not as an absent key.
    ImportDataStorage::set($post, 'referral_source', null);

    CustomFields::importer()->forModel($post)->saveValues();

    $post->load('customFieldValues');

    expect($post->getCustomFieldValue($this->referralSource))->toBeEmpty();
});

it('distinguishes a blank mapped column from an unmapped one in the same row', function (): void {
    $post = Post::factory()->create();

    $post->saveCustomFieldValue($this->referralSource, 'Continuum of Care');
    $post->saveCustomFieldValue($this->caseworker, 'Alex Rivera');

    ImportDataStorage::set($post, 'referral_source', null);

    CustomFields::importer()->forModel($post)->saveValues();

    $post->load('customFieldValues');

    expect($post->getCustomFieldValue($this->referralSource))->toBeEmpty()
        ->and($post->getCustomFieldValue($this->caseworker))->toBe('Alex Rivera');
});
