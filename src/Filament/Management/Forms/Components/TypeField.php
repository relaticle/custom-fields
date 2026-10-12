<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Relaticle\CustomFields\Collections\FieldTypeCollection;
use Relaticle\CustomFields\Data\FieldTypeData;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Support\ViewFlavor;

final class TypeField extends Select
{
    /**
     * Set up the component with a custom configuration.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $polishedView = ViewFlavor::view(UiSurface::TypePicker);

        if ($polishedView !== null) {

            $this->view($polishedView);

        }

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
     * Every field type as the grid draws it: what it is called, what it looks like, and one
     * line saying what it is for. A type a host registered without a description keeps its
     * label rather than showing an empty line.
     *
     * @return array<int, array{key: string, label: string, icon: string, description: ?string}>
     */
    public function getTypeChoices(): array
    {
        $choices = [];

        // The grid draws the Select's own options, not the registry: a consumer that narrows
        // ->options() or disables one with ->disableOptionWhen() has to narrow both flavors.
        foreach (array_keys($this->getEnabledOptions()) as $key) {
            $data = CustomFieldsType::getFieldType((string) $key);

            if (! $data instanceof FieldTypeData) {
                continue;
            }

            $choices[] = [
                'key' => $data->key,
                'label' => $data->label,
                'icon' => $data->icon,
                'description' => $this->description($data),
            ];
        }

        return $choices;
    }

    /**
     * Type keys are hyphenated and lang keys are not, the same way the type labels already
     * resolve, so a description is found under the key its label uses.
     */
    private function description(FieldTypeData $data): ?string
    {
        $key = 'custom-fields::custom-fields.field_type_descriptions.'.str_replace('-', '_', $data->key);

        return Lang::has($key) ? __($key) : null;
    }

    /**
     * Get all formatted options.
     *
     * @return array<string, string>
     */
    protected function getAllFormattedOptions(): array
    {
        return $this->selectableTypes()
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

        return $this->selectableTypes()
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

    private function selectableTypes(): FieldTypeCollection
    {
        $record = $this->getRecord();

        return CustomFieldsType::toCollection()->selectable($record instanceof CustomField ? $record->type : null);
    }
}
