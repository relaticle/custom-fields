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
use Livewire\Component;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Livewire\Concerns\CreatesCustomFields;
use Relaticle\CustomFields\Livewire\Concerns\ManagesFields;
use Relaticle\CustomFields\Models\CustomFieldSection;

/**
 * Livewire component for managing custom fields when sections are disabled.
 */
final class ManageFieldsWithoutSections extends Component implements HasActions, HasForms
{
    use CreatesCustomFields;
    use InteractsWithActions;
    use InteractsWithForms;
    use ManagesFields;

    public string $entityType;

    public CustomFieldSection $section;

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
            ->fillForm(['entity_type' => $this->entityType])
            ->mutateDataUsing(fn (array $data): array => $this->mutateFieldData($data, $this->entityType, $this->section->getKey()))
            ->action(fn (array $data) => $this->storeField($data))
            ->modalWidth(Width::ScreenLarge)
            ->slideOver();
    }

    public function render(): View
    {
        return view('custom-fields::livewire.manage-fields-without-sections');
    }
}
