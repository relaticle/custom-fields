<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Relaticle\CustomFields\Models\CustomFieldLink;
use Relaticle\CustomFields\Models\CustomFieldRelationship;

/**
 * @extends Factory<CustomFieldLink>
 */
final class CustomFieldLinkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CustomFieldLink>
     */
    protected $model = CustomFieldLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'relationship_id' => CustomFieldRelationship::factory(),
            'from_entity_type' => 'post',
            'from_entity_id' => $this->faker->randomNumber(),
            'to_entity_type' => 'user',
            'to_entity_id' => $this->faker->randomNumber(),
            'sort_order' => 0,
            'active_from' => Carbon::now(),
            'active_until' => null,
            'source' => CustomFieldLink::SOURCE_USER,
        ];
    }
}
