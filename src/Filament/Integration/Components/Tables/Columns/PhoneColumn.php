<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresSearchable;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Services\Phone\CountryPhoneService;

final class PhoneColumn extends AbstractTableColumn
{
    use ConfiguresColumnLabel;
    use ConfiguresSearchable;

    public function __construct(
        private readonly CountryPhoneService $phoneService,
    ) {}

    public function make(CustomField $customField): BaseTextColumn
    {
        $column = BaseTextColumn::make($customField->getFieldName())
            ->view('custom-fields::tables.columns.phone-column');

        $this->configureLabel($column, $customField);
        $this->configureSearchable($column, $customField);

        $column
            ->width('180px')
            ->disabledClick()
            ->sortable(false)
            ->getStateUsing(function (HasCustomFields $record) use ($customField): array {
                $value = $record->getCustomFieldValue($customField);

                if (! is_array($value)) {
                    $value = filled($value) ? [$value] : [];
                }

                return array_map(
                    fn (mixed $entry): array => $this->normalizeEntry($entry),
                    $value
                );
            });

        return $column;
    }

    /**
     * Normalize phone entry to {country, number, display, tel} format.
     *
     * @return array{country: string, number: string, display: string, tel: string}
     */
    private function normalizeEntry(mixed $entry): array
    {
        $defaultCountry = $this->phoneService->detectCountryFromLocale();

        if (is_string($entry) && ($entry !== '' && $entry !== '0')) {
            // E.164 string format
            $parsed = $this->phoneService->parseE164($entry, $defaultCountry);

            return [
                'country' => $parsed['country'],
                'number' => $parsed['number'],
                'display' => $entry,
                'tel' => preg_replace('/[^0-9+]/', '', $entry),
            ];
        }

        if (is_array($entry) && ! empty($entry['number'])) {
            // Object format with country and number
            $country = $entry['country'] ?? $defaultCountry;
            $countryCode = $this->phoneService->getCallingCode($country);
            $display = '+'.$countryCode.' '.$entry['number'];
            $digitsOnly = preg_replace('/[^0-9]/', '', $entry['number']);

            return [
                'country' => $country,
                'number' => $entry['number'],
                'display' => $display,
                'tel' => '+'.$countryCode.$digitsOnly,
            ];
        }

        return [
            'country' => $defaultCountry,
            'number' => '',
            'display' => '',
            'tel' => '',
        ];
    }
}
