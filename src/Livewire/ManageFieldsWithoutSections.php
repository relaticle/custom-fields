<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\TenantContextService;
use Relaticle\CustomFields\Support\CodeGenerator;

/**
 * Livewire component for managing custom fields when sections are disabled.
 *
 * This component displays fields in a flat grid without any section wrapper,
 * while the default hidden section is managed in the background.
 */
final class ManageFieldsWithoutSections extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public string $entityType;

    public CustomFieldSection $section;

    #[Computed]
    public function fields(): Collection
    {
        return $this->section->fields()->withDeactivated()->orderBy('sort_order')->get();
    }

    #[On('field-width-updated')]
    public function fieldWidthUpdated(int|string $fieldId, int $width): void
    {
        $model = CustomFields::newCustomFieldModel();
        $model->where($model->getKeyName(), $fieldId)->update(['width' => $width]);

        $this->section->refresh();
    }

    #[On('field-deleted')]
    public function fieldDeleted(): void
    {
        $this->section->refresh();
    }

    #[On('fields-reordered')]
    public function fieldsReordered(): void
    {
        unset($this->fields);
    }

    public function updateFieldsOrder(array $fields): void
    {
        $model = CustomFields::newCustomFieldModel();
        foreach ($fields as $index => $field) {
            $model->query()
                ->withDeactivated()
                ->where($model->getKeyName(), $field)
                ->update([
                    'custom_field_section_id' => $this->section->getKey(),
                    'sort_order' => $index,
                ]);
        }

        $this->dispatch('fields-reordered')->self();
    }

    public function createFieldAction(): Action
    {
        return Action::make('createField')
            ->size(Size::Small)
            ->label(__('custom-fields::custom-fields.field.form.add_field'))
            ->icon('heroicon-s-plus')
            ->color('gray')
            ->button()
            ->outlined()
            ->extraAttributes([
                'class' => 'flex justify-center items-center rounded-lg border-gray-300 hover:border-gray-400 border-dashed',
            ])
            ->model(CustomFields::customFieldModel())
            ->schema(FieldForm::schema(withOptionsRelationship: false))
            ->fillForm([
                'entity_type' => $this->entityType,
            ])
            ->mutateDataUsing(function (array $data): array {
                if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                    $data[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
                }

                if (FeatureManager::isEnabled(CustomFieldsFeature::FIELD_CODE_AUTO_GENERATE) && blank($data['code'] ?? null)) {
                    $data['code'] = CodeGenerator::generateUniqueFieldCode(
                        $data['name'],
                        $this->entityType
                    );
                }

                return [
                    ...$data,
                    'entity_type' => $this->entityType,
                    'custom_field_section_id' => $this->section->getKey(),
                ];
            })
            ->action(function (array $data): void {
                $options = collect($data['options'] ?? [])
                    ->filter()
                    ->map(function (array $option): array {
                        if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                            $option[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
                        }

                        return $option;
                    })
                    ->values();

                unset($data['options']);

                $customField = CustomFields::newCustomFieldModel()->create($data);

                $customField->options()->createMany($options);
            })
            ->modalWidth(Width::ScreenLarge)
            ->slideOver();
    }

    public function render(): View
    {
        return view('custom-fields::livewire.manage-fields-without-sections');
    }
}
