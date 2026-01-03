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
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Livewire\Concerns\CreatesCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;

/**
 * Livewire component for managing custom fields in a table layout.
 *
 * Shows ALL fields for the entity type with search, reordering, and inline editing.
 * Used when sections are disabled (DISABLE_FIELD_SECTIONS feature).
 */
final class ManageFieldsTable extends Component implements HasActions, HasForms
{
    use CreatesCustomFields;
    use InteractsWithActions;
    use InteractsWithForms;

    public string $entityType;

    /** The default section used for creating new fields */
    public CustomFieldSection $section;

    public string $search = '';

    /** @return Collection<int, CustomField> */
    #[Computed]
    public function activeFields(): Collection
    {
        return $this->getFieldsQuery()
            ->where('active', true)
            ->get();
    }

    /** @return Collection<int, CustomField> */
    #[Computed]
    public function inactiveFields(): Collection
    {
        return $this->getFieldsQuery()
            ->where('active', false)
            ->get();
    }

    protected function getFieldsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = CustomFields::newCustomFieldModel()
            ->newQuery()
            ->withDeactivated()
            ->where('entity_type', $this->entityType)
            ->orderBy('sort_order');

        if ($this->search !== '') {
            $query->where('name', 'like', "%{$this->search}%");
        }

        return $query;
    }

    protected function findField(string $fieldId): ?CustomField
    {
        return CustomFields::newCustomFieldModel()
            ->newQuery()
            ->withDeactivated()
            ->find($fieldId);
    }

    public function updateFieldsOrder(array $order): void
    {
        foreach ($order as $index => $id) {
            CustomFields::newCustomFieldModel()
                ->where('id', $id)
                ->update(['sort_order' => $index]);
        }

        unset($this->activeFields, $this->inactiveFields);
    }

    public function updatedSearch(): void
    {
        unset($this->activeFields, $this->inactiveFields);
    }

    public function editFieldAction(): Action
    {
        return Action::make('editField')
            ->label(__('filament-actions::edit.single.label'))
            ->icon('heroicon-o-pencil-square')
            ->model(CustomFields::customFieldModel())
            ->record(fn (array $arguments): ?CustomField => $this->findField($arguments['fieldId']))
            ->schema(FieldForm::schema(withOptionsRelationship: true))
            ->fillForm(fn (CustomField $record): array => $record->toArray())
            ->action(function (array $data, CustomField $record): void {
                $record->update($data);
                unset($this->activeFields, $this->inactiveFields);
            })
            ->modalWidth(Width::ScreenLarge)
            ->slideOver();
    }

    public function activateFieldAction(): Action
    {
        return Action::make('activateField')
            ->label(__('custom-fields::custom-fields.field.actions.activate'))
            ->icon('heroicon-o-archive-box')
            ->action(function (array $arguments): void {
                $field = $this->findField($arguments['fieldId']);
                $field?->activate();
                unset($this->activeFields, $this->inactiveFields);
            });
    }

    public function deactivateFieldAction(): Action
    {
        return Action::make('deactivateField')
            ->label(__('custom-fields::custom-fields.field.actions.deactivate'))
            ->icon('heroicon-o-archive-box-x-mark')
            ->action(function (array $arguments): void {
                $field = $this->findField($arguments['fieldId']);
                if ($field && ! $field->isSystemDefined()) {
                    $field->deactivate();
                    unset($this->activeFields, $this->inactiveFields);
                }
            });
    }

    public function deleteFieldAction(): Action
    {
        return Action::make('deleteField')
            ->label(__('filament-actions::delete.single.label'))
            ->requiresConfirmation()
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->action(function (array $arguments): void {
                $field = $this->findField($arguments['fieldId']);
                if ($field && ! $field->isSystemDefined()) {
                    $field->delete();
                    unset($this->activeFields, $this->inactiveFields);
                }
            });
    }

    #[On('field-width-updated')]
    public function fieldWidthUpdated(int|string $fieldId, int $width): void
    {
        $model = CustomFields::newCustomFieldModel();
        $model->where($model->getKeyName(), $fieldId)->update(['width' => $width]);
        unset($this->activeFields, $this->inactiveFields);
    }

    #[On('field-deleted')]
    public function fieldDeleted(): void
    {
        unset($this->activeFields, $this->inactiveFields);
    }

    #[On('fields-reordered')]
    public function fieldsReordered(): void
    {
        unset($this->activeFields, $this->inactiveFields);
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
            ->action(function (array $data): void {
                $this->storeField($data);
                unset($this->activeFields, $this->inactiveFields);
            })
            ->modalWidth(Width::ScreenLarge)
            ->slideOver();
    }

    public function render(): View
    {
        return view('custom-fields::livewire.manage-fields-table');
    }
}
