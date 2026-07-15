<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Relaticle\CustomFields\Data\CustomFieldSectionSettingsData;
use Relaticle\CustomFields\Enums\CustomFieldSectionType;
use Relaticle\CustomFields\Enums\CustomFieldWidth;
use Relaticle\CustomFields\Models\CustomFieldSection;

class CustomFieldSectionFactory extends Factory
{
    protected $model = CustomFieldSection::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'code' => $this->faker->unique()->slug(),
            'type' => CustomFieldSectionType::SECTION,
            'entity_type' => 'App\\Models\\User',
            'settings' => new CustomFieldSectionSettingsData,
            'sort_order' => $this->faker->numberBetween(0, 100),
            'active' => true,
            'system_defined' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function systemDefined(): static
    {
        return $this->state(fn (array $attributes) => [
            'system_defined' => true,
        ]);
    }

    public function forEntityType(string $entityType): static
    {
        return $this->state(fn (array $attributes) => [
            'entity_type' => $entityType,
        ]);
    }

    public function headless(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CustomFieldSectionType::HEADLESS,
        ]);
    }

    public function fieldset(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => CustomFieldSectionType::FIELDSET,
        ]);
    }

    public function width(CustomFieldWidth $width): static
    {
        return $this->state(fn (array $attributes): array => [
            'width' => $width,
        ]);
    }
}
