<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

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
    public function evaluate(array $fieldValues): bool
    {
        if (! $this->requiresConditions() || ! $this->conditions instanceof DataCollection) {
            return $this->mode === VisibilityMode::ALWAYS_VISIBLE;
        }

        $results = [];

        foreach ($this->conditions as $condition) {
            $result = $this->evaluateCondition($condition, $fieldValues);
            $results[] = $result;
        }

        $conditionsMet = $this->logic->evaluate($results);

        return $this->mode->shouldShow($conditionsMet);
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     */
    protected function evaluateCondition(VisibilityConditionData $condition, array $fieldValues): bool
    {
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
            $fields[] = $condition->field_code;
        }

        return array_unique($fields);
    }
}
