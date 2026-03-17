<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class VisibilityData extends Data
{
    /**
     * @param  DataCollection<int, VisibilityConditionData>|null  $conditions
     */
    public function __construct(
        public VisibilityMode $mode = VisibilityMode::ALWAYS_VISIBLE,
        public VisibilityLogic $logic = VisibilityLogic::ALL,
        #[DataCollectionOf(VisibilityConditionData::class)]
        public ?DataCollection $conditions = null,
        public bool $alwaysSave = false,
    ) {}

    public function requiresConditions(): bool
    {
        return $this->mode->requiresConditions();
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     */
    public function evaluate(array $fieldValues, ?Model $record = null): bool
    {
        if (! $this->requiresConditions() || ! $this->conditions instanceof DataCollection) {
            return $this->mode === VisibilityMode::ALWAYS_VISIBLE;
        }

        $modelAttributesEnabled = FeatureManager::isEnabled(CustomFieldsFeature::MODEL_ATTRIBUTE_CONDITIONS);

        $results = [];

        foreach ($this->conditions as $condition) {
            if ($condition->isModelAttribute() && ! $modelAttributesEnabled) {
                continue;
            }

            $results[] = $this->evaluateCondition($condition, $fieldValues, $record);
        }

        if ($results === []) {
            return true;
        }

        $conditionsMet = $this->logic->evaluate($results);

        return $this->mode->shouldShow($conditionsMet);
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     */
    private function evaluateCondition(VisibilityConditionData $condition, array $fieldValues, ?Model $record = null): bool
    {
        if ($condition->isModelAttribute()) {
            if (! $record instanceof Model) {
                return true;
            }

            $fieldValue = $record->getAttribute($condition->field_code);

            return $condition->operator->evaluate($fieldValue, $condition->value);
        }

        $fieldValue = $fieldValues[$condition->field_code] ?? null;

        return $condition->operator->evaluate($fieldValue, $condition->value);
    }

    /**
     * @return array<int, string>
     */
    public function getDependentFields(): array
    {
        if (! $this->requiresConditions() || ! $this->conditions instanceof DataCollection) {
            return [];
        }

        $fields = [];

        foreach ($this->conditions as $condition) {
            if ($condition->isCustomField()) {
                $fields[] = $condition->field_code;
            }
        }

        return array_unique($fields);
    }

    public function hasModelAttributeConditions(): bool
    {
        if (! $this->conditions instanceof DataCollection) {
            return false;
        }

        foreach ($this->conditions as $condition) {
            if ($condition->isModelAttribute()) {
                return true;
            }
        }

        return false;
    }
}
