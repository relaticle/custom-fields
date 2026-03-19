<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class CurrencyComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): TextInput
    {
        $decimalPlaces = (int) ($customField->validation_rules->get('decimal_places') ?? 2);

        return TextInput::make($customField->getFieldName())
            ->prefix('$')
            ->numeric()
            ->inputMode('decimal')
            ->step($decimalPlaces > 0 ? 1 / (10 ** $decimalPlaces) : 1)
            ->formatStateUsing(function (mixed $state) use ($decimalPlaces): ?string {
                if ($state === null || $state === '') {
                    return null;
                }

                return number_format((float) $state, $decimalPlaces);
            })
            ->dehydrateStateUsing(function (mixed $state): ?float {
                if ($state === null || $state === '') {
                    return null;
                }

                return Str::of($state)->replace(['$', ','], '')->toFloat();
            });
    }
}
