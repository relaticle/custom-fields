<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Relaticle\CustomFields\Data\CustomFieldSettingsData;
use Relaticle\CustomFields\Data\VisibilityConditionData;
use Relaticle\CustomFields\Data\VisibilityData;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\Enums\VisibilityOperator;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Tests\Fixtures\Models\User;
use Spatie\LaravelData\DataCollection;

/**
 * @extends Factory<CustomField>
 */
final class CustomFieldFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CustomField>
     */
    protected $model = CustomField::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->word(),
            'name' => $this->faker->name(),
            'type' => $this->faker->randomElement(['text', 'number', 'link', 'textarea', 'date', 'date_time', 'checkbox', 'checkbox_list', 'radio', 'select', 'multi_select', 'rich_editor', 'markdown_editor', 'tags_input', 'color_picker', 'toggle', 'toggle_buttons', 'currency']),
            'entity_type' => User::class,
            'sort_order' => 1,
            'validation_rules' => [],
            'active' => true,
            'system_defined' => false,
            'settings' => new CustomFieldSettingsData(
                encrypted: false
            ),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * Configure the field with specific validation rules.
     *
     * @param  array<string, mixed>  $rules
     */
    public function withValidation(array $rules): self
    {
        return $this->state(fn () => ['validation_rules' => $rules]);
    }

    /**
     * Configure the field with visibility conditions.
     *
     * @param  array<array{field_code: string, operator: string, value: mixed}>  $conditions
     */
    public function withVisibility(array $conditions): self
    {
        return $this->state(function (array $attributes) use ($conditions) {
            $visibilityConditions = new DataCollection(
                VisibilityConditionData::class,
                array_map(
                    fn (array $condition) => new VisibilityConditionData(
                        field_code: $condition['field_code'],
                        operator: VisibilityOperator::from($condition['operator']),
                        value: $condition['value']
                    ),
                    $conditions
                )
            );

            $existingSettings = $attributes['settings'] ?? new CustomFieldSettingsData;
            if (is_array($existingSettings)) {
                $existingSettings = new CustomFieldSettingsData(...$existingSettings);
            }

            return [
                'settings' => new CustomFieldSettingsData(
                    visible_in_list: $existingSettings->visible_in_list,
                    list_toggleable_hidden: $existingSettings->list_toggleable_hidden,
                    visible_in_view: $existingSettings->visible_in_view,
                    searchable: $existingSettings->searchable,
                    encrypted: $existingSettings->encrypted,
                    enable_option_colors: $existingSettings->enable_option_colors,
                    visibility: new VisibilityData(
                        mode: VisibilityMode::SHOW_WHEN,
                        conditions: $visibilityConditions
                    )
                ),
            ];
        });
    }

    /**
     * Create a field with options (for select, radio, etc.).
     *
     * @param  array<int, string>  $options
     */
    public function withOptions(array $options): self
    {
        return $this->state(function (array $attributes) {
            $existingSettings = $attributes['settings'] ?? new CustomFieldSettingsData;
            if (is_array($existingSettings)) {
                $existingSettings = new CustomFieldSettingsData(...$existingSettings);
            }

            return [
                'settings' => new CustomFieldSettingsData(
                    visible_in_list: $existingSettings->visible_in_list,
                    list_toggleable_hidden: $existingSettings->list_toggleable_hidden,
                    visible_in_view: $existingSettings->visible_in_view,
                    searchable: $existingSettings->searchable,
                    encrypted: $existingSettings->encrypted,
                    enable_option_colors: true,
                    visibility: $existingSettings->visibility
                ),
            ];
        })->afterCreating(function (CustomField $customField) use ($options) {
            foreach ($options as $index => $option) {
                $customField->options()->create([
                    'name' => $option,
                    'sort_order' => $index + 1,
                ]);
            }
        });
    }

    /**
     * Create an encrypted field.
     */
    public function encrypted(): self
    {
        return $this->state(fn (array $attributes) => [
            'settings' => new CustomFieldSettingsData(
                encrypted: true
            ),
        ]);
    }

    /**
     * Create an inactive field.
     */
    public function inactive(): self
    {
        return $this->state(['active' => false]);
    }

    /**
     * Create a system-defined field.
     */
    public function systemDefined(): self
    {
        return $this->state(['system_defined' => true]);
    }

    /**
     * Create a field of specific type with appropriate validation.
     */
    public function ofType(string $type): self
    {
        return $this->state([
            'type' => $type,
            'validation_rules' => [],
        ]);
    }

    /**
     * Create a field with required validation.
     */
    public function required(): self
    {
        return $this->state(function (array $attributes) {
            $validationRules = $attributes['validation_rules'] ?? [];
            $validationRules['required'] = true;

            return ['validation_rules' => $validationRules];
        });
    }

    /**
     * Create a field with min/max length validation.
     */
    public function withLength(?int $min = null, ?int $max = null): self
    {
        return $this->state(function (array $attributes) use ($min, $max) {
            $validationRules = $attributes['validation_rules'] ?? [];

            if ($min !== null) {
                $validationRules['min_length'] = $min;
            }

            if ($max !== null) {
                $validationRules['max_length'] = $max;
            }

            return ['validation_rules' => $validationRules];
        });
    }

    /**
     * Create a field with complex conditional visibility.
     */
    public function conditionallyVisible(string $dependsOnFieldCode, string $operator, mixed $value): self
    {
        return $this->withVisibility([
            [
                'field_code' => $dependsOnFieldCode,
                'operator' => $operator,
                'value' => $value,
            ],
        ]);
    }
}
