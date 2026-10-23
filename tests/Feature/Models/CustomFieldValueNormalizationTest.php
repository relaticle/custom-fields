<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Models\CustomFieldValue;
use Relaticle\CustomFields\Support\SafeValueConverter;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());

    $section = CustomFieldSection::factory()->forEntityType(Post::class)->create(['active' => true]);

    $this->linkField = CustomField::factory()->create([
        'custom_field_section_id' => $section->getKey(),
        'entity_type' => Post::class,
        'code' => 'website',
        'name' => 'Website',
        'type' => 'link',
        'settings' => new CustomFieldSettingsData(allow_multiple: true, max_values: 5),
    ]);
});

class TrimmedTextFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('trimmed-text')
            ->label('Trimmed text')
            ->icon('heroicon-o-pencil');
    }

    public function setValue(string $value): string
    {
        return trim($value);
    }
}

it('keeps the scheme of a link saved outside the panel form', function (): void {
    $post = Post::factory()->create();

    $post->saveCustomFieldValue($this->linkField, ['HTTPS://Example.com/Pricing/']);

    $stored = CustomFieldValue::query()->where('entity_id', $post->getKey())->where('custom_field_id', $this->linkField->getKey())->firstOrFail();

    expect(collect($stored->json_value)->all())->toBe(['https://example.com/Pricing']);
});

it('collapses values that normalize to the same link', function (): void {
    expect(SafeValueConverter::toDbSafe(['https://example.com', 'HTTPS://Example.com/', ' https://example.com '], 'link', $this->linkField))
        ->toBe(['https://example.com']);
});

it('keeps numeric-looking values that differ as text', function (): void {
    expect(SafeValueConverter::toDbSafe(['007', '7', '1e1', '10'], 'link', $this->linkField))
        ->toBe(['007', '7', '1e1', '10']);
});

it('leaves values untouched when no field is given', function (): void {
    expect(SafeValueConverter::toDbSafe(['https://example.com'], 'link'))->toBe(['https://example.com']);
});

it('stores a phone saved outside the panel form as E.164', function (): void {
    $phoneField = CustomField::factory()->create([
        'custom_field_section_id' => $this->linkField->custom_field_section_id,
        'entity_type' => Post::class,
        'code' => 'phone',
        'name' => 'Phone',
        'type' => 'phone',
        'settings' => new CustomFieldSettingsData(allow_multiple: true, max_values: 5),
    ]);
    $post = Post::factory()->create();

    $post->saveCustomFieldValue($phoneField, ['+1 (415) 555-0100', '+1-415-555-0100']);

    $stored = CustomFieldValue::query()->where('entity_id', $post->getKey())->where('custom_field_id', $phoneField->getKey())->firstOrFail();

    expect(collect($stored->json_value)->all())->toBe(['+14155550100']);
});

it('keeps a phone extension and stays stable when saved twice', function (): void {
    $phoneField = CustomField::factory()->create([
        'custom_field_section_id' => $this->linkField->custom_field_section_id,
        'entity_type' => Post::class,
        'code' => 'phone',
        'name' => 'Phone',
        'type' => 'phone',
        'settings' => new CustomFieldSettingsData(allow_multiple: true, max_values: 5),
    ]);
    $once = SafeValueConverter::toDbSafe(['+1 (415) 555-0100 ext. 12'], 'phone', $phoneField);

    expect($once)->toBe(['+14155550100;ext=12'])
        ->and(SafeValueConverter::toDbSafe($once, 'phone', $phoneField))->toBe($once);
});

it('adds no scheme to a url-variant link typed without one', function (): void {
    expect(SafeValueConverter::toDbSafe(['Acme.com/Path/', 'acme.com', 'N/A'], 'link', $this->linkField))->toBe(['acme.com/Path', 'acme.com', 'N/A']);
});

it('normalizes a url-variant link to the same value when applied twice', function (): void {
    $once = SafeValueConverter::toDbSafe(['HTTPS://Acme.com/a/?x=1#top', 'http://acme.com//'], 'link', $this->linkField);

    expect($once)->toBe(['https://acme.com/a/?x=1#top', 'http://acme.com'])
        ->and(SafeValueConverter::toDbSafe($once, 'link', $this->linkField))->toBe($once);
});

it('ignores a link_variant setting and keeps the path', function (): void {
    $this->linkField->update(['settings' => new CustomFieldSettingsData(
        allow_multiple: true,
        max_values: 5,
        additional: ['link_variant' => 'domain'],
    )]);

    expect(SafeValueConverter::toDbSafe(['HTTPS://www.LinkedIn.com/Company/Acme', '  http://acme.com/Path  '], 'link', $this->linkField->refresh()))
        ->toBe(['https://www.linkedin.com/Company/Acme', 'http://acme.com/Path']);
});

it('drops url-variant list items that hold no host', function (): void {
    expect(SafeValueConverter::toDbSafe(['https://', '   ', 'https://example.com'], 'link', $this->linkField))->toBe(['https://example.com']);
});

it('stores nothing for a scalar that normalizes to nothing', function (): void {
    CustomFieldsType::register(['trimmed-text' => TrimmedTextFieldType::class]);
    $textField = CustomField::factory()->create([
        'custom_field_section_id' => $this->linkField->custom_field_section_id,
        'entity_type' => Post::class,
        'code' => 'trimmed',
        'name' => 'Trimmed',
        'type' => 'trimmed-text',
    ]);

    expect(SafeValueConverter::toDbSafe('   ', 'trimmed-text', $textField))->toBeNull()
        ->and(SafeValueConverter::toDbSafe(' hello ', 'trimmed-text', $textField))->toBe('hello');
});
