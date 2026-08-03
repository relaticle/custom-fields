<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Builders;

use Filament\Actions\Imports\ImportColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportColumnConfigurator;
use Relaticle\CustomFields\Filament\Integration\Support\Imports\ImportDataStorage;
use Relaticle\CustomFields\Models\CustomField;

final class ImporterBuilder extends BaseBuilder
{
    public function columns(): Collection
    {
        return $this->getAllFields()
            ->map(fn (CustomField $field): ImportColumn => $this->createColumn($field))
            ->values();
    }

    private function createColumn(CustomField $field): ImportColumn
    {
        $column = ImportColumn::make('custom_fields_'.$field->code)
            ->label($field->name)
            ->guess($this->generateGuesses($field));

        // Use the unified configurator
        app(ImportColumnConfigurator::class)->configure($column, $field);

        return $column;
    }

    /**
     * Generate guess aliases from custom field name and code.
     *
     * For a field with name="Job Title" and code="job_title":
     * Returns: ['job_title', 'job title', 'jobtitle', 'Job Title', 'custom_fields_job_title']
     *
     * @return array<string>
     */
    private function generateGuesses(CustomField $field): array
    {
        $guesses = [];

        $guesses[] = $field->code;
        $guesses[] = str_replace('_', ' ', $field->code);
        $guesses[] = str_replace(['_', ' ', '-'], '', $field->code);
        $guesses[] = $field->name;
        $guesses[] = strtolower($field->name);
        $guesses[] = 'custom_fields_'.$field->code;

        // Add semantic aliases based on field type
        $guesses = array_merge($guesses, $this->getSemanticAliases($field));

        return array_values(array_unique($guesses));
    }

    /**
     * Get semantic aliases based on field type and code.
     *
     * Uses the field type key from FieldTypeSystem/Definitions/*.php
     * to add common column name variations for better CSV mapping.
     *
     * @return array<string>
     */
    private function getSemanticAliases(CustomField $field): array
    {
        $code = strtolower($field->code);

        // Type-based aliases (most reliable - uses field type definition)
        $typeAliases = match ($field->type) {
            'phone' => [
                'phone', 'mobile', 'cell', 'telephone', 'tel',
                'phone number', 'mobile number', 'cell number',
                'contact phone', 'work phone', 'home phone',
            ],
            'email' => [
                'email', 'e-mail', 'mail', 'email address',
                'work email', 'personal email', 'contact email',
            ],
            'link' => [
                'url', 'link', 'website', 'web', 'site',
                'homepage', 'web address',
            ],
            default => [],
        };

        // Code-based aliases (fallback for text fields with semantic names)
        $codeAliases = [];

        if (str_contains($code, 'phone') || str_contains($code, 'mobile') || str_contains($code, 'cell')) {
            $codeAliases = array_merge($codeAliases, [
                'phone', 'mobile', 'cell', 'telephone', 'tel',
                'phone number', 'mobile number', 'cell number',
            ]);
        }

        if (str_contains($code, 'linkedin')) {
            $codeAliases = array_merge($codeAliases, [
                'linkedin', 'linked in', 'linkedin url', 'linkedin profile',
            ]);
        }

        return array_unique(array_merge($typeAliases, $codeAliases));
    }

    /**
     * Persist only the fields the imported row actually carried.
     *
     * saveCustomFields() nulls every field missing from its payload, which is right for a form
     * (it renders all fields, so absent means cleared) and destructive for an import, where a CSV
     * legitimately maps a subset of columns and absent means "not in this file".
     */
    public function saveValues(?Model $tenant = null): void
    {
        $customFieldsData = ImportDataStorage::pull($this->model);

        if ($customFieldsData === []) {
            return;
        }

        $this->getAllFields()
            ->filter(fn (CustomField $field): bool => array_key_exists($field->code, $customFieldsData))
            ->each(function (CustomField $field) use ($customFieldsData, $tenant): void {
                $this->model->saveCustomFieldValue($field, $customFieldsData[$field->code], $tenant);
            });
    }

    public function filterCustomFieldsFromData(array $data): array
    {
        return array_filter(
            $data,
            fn (string $key): bool => ! str_starts_with($key, 'custom_fields_'),
            ARRAY_FILTER_USE_KEY
        );
    }
}
