<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\PhoneInput\PhoneInputComponent;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\Phone\CountryPhoneService;

final readonly class PhoneComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): PhoneInputComponent
    {
        $allowMultiple = $customField->settings->allow_multiple ?? false;
        $maxValues = $allowMultiple
            ? ($customField->settings->max_values ?? 10)
            : 1;

        return PhoneInputComponent::make($customField->getFieldName())
            ->allowMultiple($allowMultiple)
            ->maxValues($maxValues)
            ->addLabel(__('custom-fields::custom-fields.phone.add_phone_placeholder'))
            ->placeholder(__('custom-fields::custom-fields.phone.add_phone_placeholder'))
            ->nestedRecursiveRules(['phone:AUTO', 'distinct'])
            ->rules(['array', 'max:'.$maxValues]);
    }

    /**
     * Transform E.164 strings from storage into {country, number} objects for the UI.
     */
    protected function transformHydratedValue(mixed $value, CustomField $customField): mixed
    {
        if (! is_array($value)) {
            $value = filled($value) ? [$value] : [];
        }

        if ($value === []) {
            $defaultCountry = app(CountryPhoneService::class)->detectCountryFromLocale();

            return [['country' => $defaultCountry, 'number' => '']];
        }

        $service = app(CountryPhoneService::class);
        $defaultCountry = $service->detectCountryFromLocale();

        return array_map(
            fn (mixed $e164): array => is_string($e164)
                ? $service->parseE164($e164, $defaultCountry)
                : (is_array($e164) ? $e164 : ['country' => $defaultCountry, 'number' => '']),
            $value
        );
    }
}
