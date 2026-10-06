<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Models\CustomFieldRelationship;

/**
 * @extends Factory<CustomFieldRelationship>
 */
final class CustomFieldRelationshipFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CustomFieldRelationship>
     */
    protected $model = CustomFieldRelationship::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->word(),
            'from_entity_type' => 'post',
            'to_entity_type' => 'user',
            'cardinality' => RelationshipCardinality::ManyToMany,
            'from_field_id' => null,
            'to_field_id' => null,
            'is_symmetric' => false,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
