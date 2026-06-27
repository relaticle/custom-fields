<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Cache;
use Relaticle\CustomFields\Data\FieldTypeData;
use Relaticle\CustomFields\Facades\CustomFieldsType;

class TypeField extends Select
{
    /**
     * Set up the component with a custom configuration.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->native(false)
            ->allowHtml()
            ->searchable()
            ->selectablePlaceholder(false)
            ->searchDebounce(300)
            ->searchPrompt(__('custom-fields::custom-fields.field.form.type_search_prompt'))
            ->noSearchResultsMessage(__('custom-fields::custom-fields.field.form.type_no_results'))
            ->searchingMessage(__('custom-fields::custom-fields.common.searching'))
            ->getSearchResultsUsing(fn (string $search): array => $this->getSearchResults($search))
            ->options(fn (): array => $this->getAllFormattedOptions());
    }

    /**
     * Get all formatted options.
     *
     * @return array<string, string>
     */
    protected function getAllFormattedOptions(): array
    {
        return CustomFieldsType::toCollection()
            ->mapWithKeys(fn (FieldTypeData $data): array => [$data->key => $this->getHtmlOption($data)])
            ->toArray();
    }

    /**
     * Get search results for the field types.
     *
     * @param  string  $search  The search query
     * @return array<string, string> The filtered and formatted options
     */
    public function getSearchResults(string $search): array
    {
        if (blank($search)) {
            return $this->getAllFormattedOptions();
        }

        $searchLower = mb_strtolower(trim($search));

        return CustomFieldsType::toCollection()
            ->filter(function (FieldTypeData $data) use ($searchLower): bool {
                return str_contains(mb_strtolower($data->label), $searchLower) ||
                       str_contains(mb_strtolower($data->key), $searchLower);
            })
            ->mapWithKeys(fn (FieldTypeData $data): array => [$data->key => $this->getHtmlOption($data)])
            ->toArray();
    }

    /**
     * Render an HTML option for the select field.
     *
     * @return string The rendered HTML for the option
     */
    public function getHtmlOption(FieldTypeData $data): string
    {
        $cacheKey = 'custom-fields-type-field-view-'.$data->key;

        return Cache::remember(
            key: $cacheKey,
            ttl: 60,
            callback: function () use ($data): string {
                /** @var view-string $viewName */
                $viewName = 'custom-fields::filament.forms.type-field';

                return (string) view($viewName)
                    ->with([
                        'label' => $data->label,
                        'value' => $data->key,
                        'icon' => $data->icon,
                        'selected' => $this->getState(),
                    ])
                    ->render();
            }
        );
    }
}
