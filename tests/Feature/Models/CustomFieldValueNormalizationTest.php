<?php

declare(strict_types=1);

use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\Definitions\LinkFieldType;
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

    $this->domainLinkField = CustomField::factory()->create([
        'custom_field_section_id' => $section->getKey(),
        'entity_type' => Post::class,
        'code' => 'domain',
        'name' => 'Domain',
        'type' => 'link',
        'settings' => new CustomFieldSettingsData(
            allow_multiple: true,
            max_values: 5,
            additional: ['link_variant' => 'domain'],
        ),
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

dataset('domain links', [
    'scheme, www and path' => 'https://www.Acme.com/pricing?x=1#top',
    'bare' => 'acme.com',
    'userinfo and port' => 'http://user:secret@acme.com:8080/',
    'trailing dot' => 'ACME.COM.',
    'padded' => '  www.acme.com  ',
    'at sign in the query' => 'acme.com?ref=a@b.com',
    'port and trailing dot' => 'acme.com:8080.',
    'repeated www' => 'www.www.acme.com',
    'space before the path' => 'acme.com /x',
    'non-breaking space' => "\u{00A0}https://acme.com",
    'zero-width characters' => "https://\u{200B}acme.com\u{FEFF}",
    'ideographic space' => "\u{3000}acme.com",
]);

dataset('unparseable links', [
    'one slash after the scheme' => ['https:/acme.com', 'https:/acme.com'],
    'no colon after the scheme' => ['http//acme.com', 'http//acme.com'],
    'ipv6 literal' => ['http://[::1]:8080', '[::1]:8080'],
    'tel uri' => ['tel:+14155550100', 'tel:+14155550100'],
    'not a link' => ['N/A', 'N/A'],
    'stacked scheme' => ['http://http://localhost', 'localhost'],
]);

dataset('empty links', [
    'scheme only' => 'https://',
    'slash' => '/',
    'www only' => 'www.',
    'blank' => '   ',
]);

it('stores a domain-variant link as its bare lowercase host', function (string $input): void {
    expect(SafeValueConverter::toDbSafe([$input], 'link', $this->domainLinkField))->toBe(['acme.com']);
})->with('domain links');

it('normalizes a domain-variant link to the same value when applied twice', function (string $input): void {
    $once = SafeValueConverter::toDbSafe([$input], 'link', $this->domainLinkField);

    expect(SafeValueConverter::toDbSafe($once, 'link', $this->domainLinkField))->toBe($once);
})->with('domain links');

it('keeps a domain-variant value it cannot parse as a host', function (string $input, string $expected): void {
    $once = SafeValueConverter::toDbSafe([$input], 'link', $this->domainLinkField);

    expect($once)->toBe([$expected])
        ->and(SafeValueConverter::toDbSafe($once, 'link', $this->domainLinkField))->toBe($once);
})->with('unparseable links');

it('stores a domain-variant link saved outside the panel form as its bare host', function (): void {
    $post = Post::factory()->create();

    $post->saveCustomFieldValue($this->domainLinkField, ['https://www.Acme.com/pricing', 'ACME.COM', 'https://']);

    $stored = CustomFieldValue::query()->where('entity_id', $post->getKey())->where('custom_field_id', $this->domainLinkField->getKey())->firstOrFail();

    expect(collect($stored->json_value)->all())->toBe(['acme.com']);
});

it('strips stacked schemes from a domain-variant link without re-normalizing once per scheme', function (): void {
    $fieldType = new class extends LinkFieldType
    {
        public int $calls = 0;

        public function normalize(string $value, CustomField $customField): string
        {
            $this->calls++;

            return parent::normalize($value, $customField);
        }
    };

    expect($fieldType->normalize(str_repeat('http://', 290).'Acme.com/x', $this->domainLinkField))->toBe('acme.com')
        ->and($fieldType->calls)->toBeLessThanOrEqual(2);
});

it('stores a www host with no registrable part in one lowercase spelling', function (string $input): void {
    $once = SafeValueConverter::toDbSafe([$input], 'link', $this->domainLinkField);

    expect($once)->toBe(['www.co'])
        ->and(SafeValueConverter::toDbSafe($once, 'link', $this->domainLinkField))->toBe($once);
})->with([
    'upper case' => 'WWW.CO',
    'lower case' => 'www.co',
    'scheme and path' => 'https://WWW.Co/x',
    'padded' => '  Www.Co  ',
    'repeated www' => 'www.www.co',
    'trailing dot' => 'WWW.CO.',
]);

it('adds no scheme to a url-variant link typed without one', function (): void {
    expect(SafeValueConverter::toDbSafe(['Acme.com/Path/', 'acme.com', 'N/A'], 'link', $this->linkField))->toBe(['acme.com/Path', 'acme.com', 'N/A']);
});

it('normalizes a url-variant link to the same value when applied twice', function (): void {
    $once = SafeValueConverter::toDbSafe(['HTTPS://Acme.com/a/?x=1#top', 'http://acme.com//'], 'link', $this->linkField);

    expect($once)->toBe(['https://acme.com/a/?x=1#top', 'http://acme.com'])
        ->and(SafeValueConverter::toDbSafe($once, 'link', $this->linkField))->toBe($once);
});

it('keeps the path of a url-variant link', function (?string $variant): void {
    $additional = $variant === null ? [] : ['link_variant' => $variant];
    $this->linkField->update(['settings' => new CustomFieldSettingsData(
        allow_multiple: true,
        max_values: 5,
        additional: $additional,
    )]);

    expect(SafeValueConverter::toDbSafe(['HTTPS://www.LinkedIn.com/Company/Acme', '  http://acme.com/Path  '], 'link', $this->linkField->refresh()))
        ->toBe(['https://www.linkedin.com/Company/Acme', 'http://acme.com/Path']);
})->with([
    'no variant' => null,
    'url variant' => 'url',
]);

it('drops list items that normalize to nothing', function (string $empty): void {
    expect(SafeValueConverter::toDbSafe([$empty, 'https://www.Acme.com/x', $empty], 'link', $this->domainLinkField))->toBe(['acme.com'])
        ->and(SafeValueConverter::toDbSafe([$empty], 'link', $this->domainLinkField))->toBe([]);
})->with('empty links');

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
