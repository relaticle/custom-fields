<?php

declare(strict_types=1);

use Relaticle\CustomFields\Filament\Integration\Components\Forms\PhoneInput\PhoneInputComponent;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\Phone\CountryPhoneService;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;
use Relaticle\CustomFields\Tests\Fixtures\Resources\Posts\Pages\CreatePost;

beforeEach(function (): void {
    $this->service = app(CountryPhoneService::class);
    app()->setLocale('en_US');
});

describe('PhoneInputComponent Initialization', function (): void {
    it('allows setting placeholder', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->placeholder('Enter phone number');

        expect($component->getPlaceholder())->toBe('Enter phone number');
    });

    it('allows setting add label', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->addLabel('Add another phone');

        expect($component->getAddLabel())->toBe('Add another phone');
    });

    it('uses default add label when not set', function (): void {
        $component = PhoneInputComponent::make('phone');

        expect($component->getAddLabel())->toBe('Add phone number');
    });

    it('allows setting empty state label', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->emptyStateLabel('No phone number');

        expect($component->getEmptyStateLabel())->toBe('No phone number');
    });

    it('uses placeholder as empty state label when not set', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->placeholder('Custom placeholder');

        expect($component->getEmptyStateLabel())->toBe('Custom placeholder');
    });

    it('uses default empty state label when neither set', function (): void {
        $component = PhoneInputComponent::make('phone');

        expect($component->getEmptyStateLabel())->toBe('Enter phone number');
    });
});

describe('PhoneInputComponent Multi-Value Support', function (): void {
    it('defaults to single value mode', function (): void {
        $component = PhoneInputComponent::make('phone');

        expect($component->getAllowMultiple())->toBeFalse()
            ->and($component->getMaxValues())->toBe(1);
    });

    it('allows multiple phone numbers when enabled', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->allowMultiple();

        expect($component->getAllowMultiple())->toBeTrue();
    });

    it('respects max values limit', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->allowMultiple()
            ->maxValues(5);

        expect($component->getMaxValues())->toBe(5);
    });

    it('uses closure for allow multiple', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->allowMultiple(fn (): bool => true);

        expect($component->getAllowMultiple())->toBeTrue();
    });

    it('uses closure for max values', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->maxValues(fn (): int => 10);

        expect($component->getMaxValues())->toBe(10);
    });

    it('allows disabling multiple after enabling', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->allowMultiple(true)
            ->allowMultiple(false);

        expect($component->getAllowMultiple())->toBeFalse();
    });
});

describe('PhoneInputComponent Country Detection', function (): void {
    it('detects default country from US locale', function (): void {
        app()->setLocale('en_US');

        $component = PhoneInputComponent::make('phone');

        expect($component->getDefaultCountry())->toBe('US');
    });

    it('detects default country from GB locale', function (): void {
        app()->setLocale('en_GB');

        $component = PhoneInputComponent::make('phone');

        expect($component->getDefaultCountry())->toBe('GB');
    });

    it('detects default country from DE locale', function (): void {
        app()->setLocale('de_DE');

        $component = PhoneInputComponent::make('phone');

        expect($component->getDefaultCountry())->toBe('DE');
    });

    it('allows setting custom default country', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->defaultCountry('DE');

        expect($component->getDefaultCountry())->toBe('DE');
    });

    it('uses closure for default country', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->defaultCountry(fn (): string => 'FR');

        expect($component->getDefaultCountry())->toBe('FR');
    });

    it('falls back to locale detection when default country is null', function (): void {
        app()->setLocale('en_US');

        $component = PhoneInputComponent::make('phone')
            ->defaultCountry(null);

        expect($component->getDefaultCountry())->toBe('US');
    });

    it('falls back to US for invalid locale', function (): void {
        app()->setLocale('invalid');

        $component = PhoneInputComponent::make('phone');

        expect($component->getDefaultCountry())->toBe('US');
    });
});

describe('PhoneInputComponent Country Options', function (): void {
    it('provides country options with short codes', function (): void {
        $component = PhoneInputComponent::make('phone');
        $options = $component->getCountryOptions();

        expect($options)->toBeArray()
            ->and($options['US'])->toBe('US +1')
            ->and($options['GB'])->toBe('GB +44')
            ->and($options['DE'])->toBe('DE +49')
            ->and($options['AM'])->toBe('AM +374');
    });

    it('provides country options with full names', function (): void {
        $component = PhoneInputComponent::make('phone');
        $options = $component->getCountryOptionsWithNames();

        expect($options)->toBeArray()
            ->and($options['US'])->toContain('United States')
            ->and($options['US'])->toContain('(+1)')
            ->and($options['GB'])->toContain('United Kingdom')
            ->and($options['GB'])->toContain('(+44)');
    });

    it('returns sorted country options', function (): void {
        $component = PhoneInputComponent::make('phone');
        $options = $component->getCountryOptions();

        $codes = array_keys($options);
        $sortedCodes = $codes;
        sort($sortedCodes);

        expect($codes)->toBe($sortedCodes);
    });

    it('includes all major country codes', function (): void {
        $component = PhoneInputComponent::make('phone');
        $options = $component->getCountryOptions();

        $majorCountries = ['US', 'GB', 'DE', 'FR', 'JP', 'CN', 'IN', 'BR', 'AU', 'CA'];

        foreach ($majorCountries as $country) {
            expect($options)->toHaveKey($country);
        }
    });
});

describe('PhoneInputComponent View Configuration', function (): void {
    it('uses correct blade view', function (): void {
        $component = PhoneInputComponent::make('phone');

        $reflection = new ReflectionClass($component);
        $viewProperty = $reflection->getProperty('view');
        $viewProperty->setAccessible(true);

        expect($viewProperty->getValue($component))->toBe('custom-fields::forms.phone-input');
    });

    it('has state path set correctly', function (): void {
        $component = PhoneInputComponent::make('phones');

        expect($component->getName())->toBe('phones');
    });
});

describe('PhoneInputComponent Fluent API', function (): void {
    it('supports fluent method chaining', function (): void {
        $component = PhoneInputComponent::make('phone')
            ->allowMultiple(true)
            ->maxValues(5)
            ->defaultCountry('GB')
            ->placeholder('Enter phone')
            ->addLabel('Add phone')
            ->emptyStateLabel('No phone');

        expect($component->getAllowMultiple())->toBeTrue()
            ->and($component->getMaxValues())->toBe(5)
            ->and($component->getDefaultCountry())->toBe('GB')
            ->and($component->getPlaceholder())->toBe('Enter phone')
            ->and($component->getAddLabel())->toBe('Add phone')
            ->and($component->getEmptyStateLabel())->toBe('No phone');
    });

    it('each fluent method returns the component instance', function (): void {
        $component = PhoneInputComponent::make('phone');

        expect($component->allowMultiple())->toBe($component)
            ->and($component->maxValues(5))->toBe($component)
            ->and($component->defaultCountry('US'))->toBe($component)
            ->and($component->addLabel('Add'))->toBe($component)
            ->and($component->emptyStateLabel('Empty'))->toBe($component);
    });
});

describe('PhoneInputComponent Livewire Integration', function (): void {
    beforeEach(function (): void {
        $this->section = CustomFieldSection::factory()->create([
            'entity_type' => Post::class,
            'active' => true,
        ]);
    });

    it('renders phone field in form', function (): void {
        CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'code' => 'phone',
            'type' => 'phone',
            'entity_type' => Post::class,
        ]);

        livewire(CreatePost::class)
            ->assertSuccessful()
            ->assertFormFieldExists('custom_fields.phone');
    });

    it('saves phone number in E.164 format', function (): void {
        CustomField::factory()->create([
            'custom_field_section_id' => $this->section->id,
            'code' => 'contact',
            'type' => 'phone',
            'entity_type' => Post::class,
        ]);

        $newData = Post::factory()->make();

        livewire(CreatePost::class)
            ->fillForm([
                'author_id' => $newData->author->getKey(),
                'title' => $newData->title,
                'rating' => $newData->rating,
                'custom_fields' => [
                    'contact' => [['country' => 'US', 'number' => '4155551234']],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect();

        $post = Post::query()->firstWhere('title', $newData->title);
        $value = $post->customFieldValues()
            ->whereHas('customField', fn ($q) => $q->where('code', 'contact'))
            ->first();

        expect($value->getValue())->toContain('+14155551234');
    });
});
