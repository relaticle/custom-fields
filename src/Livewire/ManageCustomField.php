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
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Component;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Models\CustomField;

final class ManageCustomField extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithRecord;

    public CustomField $field;

    public function actions(): ActionGroup
    {
        return ActionGroup::make([
            $this->editAction(),
            $this->duplicateAction(),
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
            ->fillForm(fn (): array => [
                ...$this->field->toArray(),
                'options' => $this->field->options->toArray(),
            ])
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
            ->visible(fn (CustomField $record): bool => (! $record->isActive() || ! $record->hasValues()) && ! $record->isSystemDefined())
            ->action(function (): bool {
                if ($this->field->isSystemDefined()) {
                    $this->addError('system_defined', __('custom-fields::custom-fields.field.form.system_defined_cannot_delete'));

                    return false;
                }

                return $this->field->delete() && $this->dispatch('field-deleted');
            });
    }

    public function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->icon('heroicon-o-document-duplicate')
            ->requiresConfirmation()
            ->model(CustomFields::customFieldModel())
            ->record($this->field)
            ->visible(fn (CustomField $record): bool => ! $record->isSystemDefined())
            ->action(function (): void {
                $code = $this->generateUniqueCode(
                    Str::slug($this->field->code).'-copy',
                    $this->field->entity_type
                );

                $clone = $this->field->replicate([
                    'id', 'created_at', 'updated_at',
                ]);
                $clone->name = $this->field->name.' (Copy)';
                $clone->code = $code;
                $clone->system_defined = false;
                $clone->active = true;
                $clone->save();

                foreach ($this->field->options as $option) {
                    $clone->options()->create([
                        'name' => $option->getRawOriginal('name'),
                        'sort_order' => $option->sort_order,
                        'settings' => $option->getRawOriginal('settings'),
                    ]);
                }

                $this->dispatch('field-created');
            });
    }

    private function generateUniqueCode(string $baseCode, string $entityType): string
    {
        $code = $baseCode;
        $suffix = 2;

        while (CustomFields::newCustomFieldModel()::withDeactivated()
            ->where('code', $code)
            ->where('entity_type', $entityType)
            ->exists()
        ) {
            $code = $baseCode.'-'.$suffix;
            $suffix++;
        }

        return $code;
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
