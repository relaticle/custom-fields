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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Livewire\Concerns\CreatesCustomFields;
use Relaticle\CustomFields\Models\CustomField;

/**
 * Livewire component for managing custom fields in a flat table layout.
 *
 * Shows ALL fields for the entity type with search, reordering, and inline editing.
 * Used when sections are disabled (SYSTEM_SECTIONS = false).
 */
final class ManageFieldsTable extends Component implements HasActions, HasForms
{
    use CreatesCustomFields;
    use InteractsWithActions;
    use InteractsWithForms;

    public string $entityType;

    public string $search = '';

    /** @return Collection<int, CustomField> */
    #[Computed]
    public function activeFields(): Collection
    {
        return $this->getFieldsQuery()->where('active', true)->get();
    }

    /** @return Collection<int, CustomField> */
    #[Computed]
    public function inactiveFields(): Collection
    {
        return $this->getFieldsQuery()->where('active', false)->get();
    }

    private function getFieldsQuery(): Builder
    {
        return CustomFields::newCustomFieldModel()
            ->newQuery()
            ->withDeactivated()
            ->where('entity_type', $this->entityType)
            ->when($this->search, fn (Builder $q, string $s): Builder => $q->where('name', 'like', sprintf('%%%s%%', $s)))
            ->orderBy('sort_order');
    }

    private function findField(string|int $fieldId): ?CustomField
    {
        return CustomFields::newCustomFieldModel()
            ->newQuery()
            ->withDeactivated()
            ->find($fieldId);
    }

    private function resetFieldsCache(): void
    {
        unset($this->activeFields, $this->inactiveFields);
    }

    public function updateFieldsOrder(array $order): void
    {
        foreach ($order as $index => $id) {
            CustomFields::newCustomFieldModel()
                ->where('id', $id)
                ->update(['sort_order' => $index]);
        }

        $this->resetFieldsCache();
    }

    public function updatedSearch(): void
    {
        $this->resetFieldsCache();
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
                if (isset($data['settings'])) {
                    $data['settings'] = array_merge($record->settings->toArray(), $data['settings']);
                }

                $record->update($data);
                $this->resetFieldsCache();
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
                $this->findField($arguments['fieldId'])?->activate();
                $this->resetFieldsCache();
            });
    }

    public function deactivateFieldAction(): Action
    {
        return Action::make('deactivateField')
            ->label(__('custom-fields::custom-fields.field.actions.deactivate'))
            ->icon('heroicon-o-archive-box-x-mark')
            ->action(function (array $arguments): void {
                $field = $this->findField($arguments['fieldId']);
                if ($field?->isSystemDefined() === false) {
                    $field->deactivate();
                }

                $this->resetFieldsCache();
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
                if ($field?->isSystemDefined() === false) {
                    $field->delete();
                }

                $this->resetFieldsCache();
            });
    }

    #[On('field-width-updated')]
    public function fieldWidthUpdated(int|string $fieldId, int $width): void
    {
        $model = CustomFields::newCustomFieldModel();
        $model->where($model->getKeyName(), $fieldId)->update(['width' => $width]);
        $this->resetFieldsCache();
    }

    #[On(['field-created', 'field-deleted', 'fields-reordered'])]
    public function refreshFields(): void
    {
        $this->resetFieldsCache();
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
            ->mutateDataUsing(fn (array $data): array => $this->mutateFieldData($data, $this->entityType))
            ->action(function (array $data): void {
                $this->storeField($data);
                $this->resetFieldsCache();
            })
            ->modalWidth(Width::ScreenLarge)
            ->slideOver();
    }

    public function render(): View
    {
        return view('custom-fields::livewire.manage-fields-table');
    }
}
