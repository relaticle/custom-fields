<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Concerns\InteractsWithRecord;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\Width;
use Illuminate\View\View;
use Livewire\Component;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Models\CustomField;

class ManageCustomField extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithRecord;

    public CustomField $field;

    public function actions(): ActionGroup
    {
        return ActionGroup::make([
            $this->editAction(),
            $this->activateAction(),
            $this->deactivateAction(),
            $this->deleteAction(),
        ])->dropdownPlacement('bottom-end');
    }

    public function editAction(): Action
    {
        return Action::make('edit')
            ->icon('heroicon-o-pencil')
            ->model(CustomFields::customFieldModel())
            ->record($this->field)
            ->schema(FieldForm::schema())
            ->fillForm($this->field->toArray())
            ->action(fn (array $data) => $this->field->update($data))
            ->modalWidth(Width::ScreenLarge)
            ->slideOver();
    }

    public function activateAction(): Action
    {
        return Action::make('activate')
            ->icon('heroicon-o-archive-box')
            ->model(CustomFields::customFieldModel())
            ->record($this->field)
            ->visible(fn (CustomField $record): bool => ! $record->isActive())
            ->action(fn (): bool => $this->field->activate());
    }

    public function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->icon('heroicon-o-archive-box-x-mark')
            ->model(CustomFields::customFieldModel())
            ->record($this->field)
            ->visible(fn (CustomField $record): bool => $record->isActive())
            ->action(fn (): bool => $this->field->deactivate());
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->requiresConfirmation()
            ->icon('heroicon-o-trash')
            ->model(CustomFields::customFieldModel())
            ->defaultColor('danger')
            ->record($this->field)
            ->visible(fn (CustomField $record): bool => ! $record->isActive())
            ->disabled(fn (CustomField $record): bool => $record->isSystemDefined())
            ->tooltip(fn (CustomField $record): string => $record->isSystemDefined()
                    ? __('custom-fields::custom-fields.field.form.system_defined_cannot_delete')
                    : ''
            )
            ->action(function (): bool {
                if ($this->field->isSystemDefined()) {
                    $this->addError('system_defined', __('custom-fields::custom-fields.field.form.system_defined_cannot_delete'));

                    return false;
                }

                return $this->field->delete() && $this->dispatch('field-deleted');
            });
    }

    public function setWidth(int|string $fieldId, int $width): void
    {
        $this->dispatch('field-width-updated', $fieldId, $width);
    }

    public function render(): View
    {
        return view('custom-fields::livewire.manage-custom-field');
    }
}
