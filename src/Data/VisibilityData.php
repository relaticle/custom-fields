<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\ConditionSource;
use Relaticle\CustomFields\Enums\VisibilityLogic;
use Relaticle\CustomFields\Enums\VisibilityMode;
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

        $results = [];

        foreach ($this->conditions as $condition) {
            $result = $this->evaluateCondition($condition, $fieldValues, $record);
            $results[] = $result;
        }

        $conditionsMet = $this->logic->evaluate($results);

        return $this->mode->shouldShow($conditionsMet);
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     */
    private function evaluateCondition(
        VisibilityConditionData $condition,
        array $fieldValues,
        ?Model $record = null
    ): bool {
        $fieldValue = match ($condition->source) {
            ConditionSource::CustomField => $fieldValues[$condition->field_code] ?? null,
            ConditionSource::ModelAttribute => $record?->getAttribute($condition->field_code),
        };

        return $condition->operator->evaluate($fieldValue, $condition->value);
    }

    /**
     * Get dependent custom field codes (excludes model attribute conditions).
     *
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

    /**
     * Check if any conditions reference model attributes.
     */
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
