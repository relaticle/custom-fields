<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
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
use Relaticle\CustomFields\Filament\Management\Schemas\SectionForm;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Services\TenantContextService;

final class ManageCustomFieldSection extends Component implements HasActions, HasForms
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
        // Update the width
        $model = CustomFields::newCustomFieldModel();
        $model->where($model->getKeyName(), $fieldId)->update(['width' => $width]);

        // Re-fetch the fields
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

    public function updateFieldsOrder(int|string $sectionId, array $fields): void
    {
        $model = CustomFields::newCustomFieldModel();
        foreach ($fields as $index => $field) {
            $model->query()
                ->withDeactivated()
                ->where($model->getKeyName(), $field)
                ->update([
                    'custom_field_section_id' => $sectionId,
                    'sort_order' => $index,
                ]);
        }

        // Broadcast to all section components to refresh their fields
        $this->dispatch('fields-reordered')->to('manage-custom-field-section');
    }

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
            ->model(CustomFields::sectionModel())
            ->record($this->section)
            ->schema(SectionForm::entityType($this->entityType)->schema())
            ->fillForm($this->section->toArray())
            ->action(fn (array $data) => $this->section->update($data))
            ->modalWidth(Width::TwoExtraLarge);
    }

    public function activateAction(): Action
    {
        return Action::make('activate')
            ->icon('heroicon-o-archive-box')
            ->model(CustomFields::sectionModel())
            ->record($this->section)
            ->visible(fn (CustomFieldSection $record): bool => ! $record->isActive())
            ->action(fn (): bool => $this->section->activate());
    }

    public function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->icon('heroicon-o-archive-box-x-mark')
            ->model(CustomFields::sectionModel())
            ->record($this->section)
            ->visible(fn (CustomFieldSection $record): bool => $record->isActive())
            ->action(fn (): bool => $this->section->deactivate());
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->requiresConfirmation()
            ->icon('heroicon-o-trash')
            ->model(CustomFields::sectionModel())
            ->defaultColor('danger')
            ->record($this->section)
            ->visible(fn (CustomFieldSection $record): bool => ! $record->isActive())
            ->disabled(fn (CustomFieldSection $record): bool => $record->isSystemDefined() || $record->hasSystemDefinedFields())
            ->tooltip(fn (CustomFieldSection $record): string => $record->isSystemDefined()
                    ? __('custom-fields::custom-fields.section.form.system_defined_cannot_delete')
                    : ($record->hasSystemDefinedFields()
                        ? __('custom-fields::custom-fields.section.form.contains_system_fields_cannot_delete')
                        : '')
            )
            ->action(function (): bool {
                if ($this->section->isSystemDefined()) {
                    $this->addError('system_defined', __('custom-fields::custom-fields.section.form.system_defined_cannot_delete'));

                    return false;
                }

                if ($this->section->hasSystemDefinedFields()) {
                    $this->addError('system_fields', __('custom-fields::custom-fields.section.form.contains_system_fields_cannot_delete'));

                    return false;
                }

                return $this->section->delete() && $this->dispatch('section-deleted');
            });
    }

    public function createFieldAction(): Action
    {
        return Action::make('createField')
            ->size(Size::ExtraSmall)
            ->label(__('custom-fields::custom-fields.field.form.add_field'))
            ->model(CustomFields::customFieldModel())
            ->schema(FieldForm::schema(withOptionsRelationship: false))
            ->fillForm([
                'entity_type' => $this->entityType,
            ])
            ->mutateDataUsing(function (array $data): array {
                if (FeatureManager::isEnabled(CustomFieldsFeature::SYSTEM_MULTI_TENANCY)) {
                    $data[config('custom-fields.database.column_names.tenant_foreign_key')] = TenantContextService::getCurrentTenantId();
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
        return view('custom-fields::livewire.manage-custom-field-section');
    }
}
