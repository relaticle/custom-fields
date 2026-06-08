<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Relaticle\CustomFields\Tests\Fixtures\Models\Tag;

class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
        ];
    }
}
