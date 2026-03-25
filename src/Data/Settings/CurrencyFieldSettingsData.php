<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Data\Settings;

use Relaticle\CustomFields\Support\CurrencyProvider;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class CurrencyFieldSettingsData extends Data
{
    public function __construct(
        public string $currencyCode = 'USD',
        public string $displayType = 'symbol',
        public int $decimalPlaces = 2,
    ) {}

    public static function fromAdditional(array $additional): self
    {
        $code = $additional['currency_code'] ?? 'USD';

        return new self(
            currencyCode: $code,
            displayType: $additional['display_type'] ?? 'symbol',
            decimalPlaces: (int) ($additional['decimal_places'] ?? CurrencyProvider::getDecimalDigits($code)),
        );
    }
}
