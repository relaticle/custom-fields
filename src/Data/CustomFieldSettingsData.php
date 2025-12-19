<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data;

use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class CustomFieldSettingsData extends Data
{
    public function __construct(
        public bool $visible_in_list = true,
        public ?bool $list_toggleable_hidden = null,
        public bool $visible_in_view = true,
        public bool $searchable = false,
        public bool $encrypted = false,
        public bool $enable_option_colors = false,
        public VisibilityData $visibility = new VisibilityData,
        public array $additional = [],
    ) {
        if ($this->list_toggleable_hidden === null) {
            $this->list_toggleable_hidden = FeatureManager::isEnabled(CustomFieldsFeature::UI_TOGGLEABLE_COLUMNS_HIDDEN_DEFAULT);
        }
    }
}
