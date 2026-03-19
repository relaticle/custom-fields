<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data\Settings;

use Spatie\LaravelData\Data;

class CurrencyFieldSettingsData extends Data
{
    public function __construct(
        public string $currency_code = 'USD',
        public string $display_type = 'symbol',
        public int $decimal_places = 2,
        public string $grouping = 'default',
    ) {}
}
