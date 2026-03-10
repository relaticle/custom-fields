<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\CustomFieldsFeature;
use Relaticle\CustomFields\FeatureSystem\FeatureManager;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Filament\Management\Schemas\SectionForm;
use Relaticle\CustomFields\Livewire\Concerns\CreatesCustomFields;
use Relaticle\CustomFields\Livewire\Concerns\ManagesFields;
use Relaticle\CustomFields\Models\CustomFieldSection;

final class ManageCustomFieldSection extends Component implements HasActions, HasForms
{
    use CreatesCustomFields;
    use InteractsWithActions;
    use InteractsWithForms;
    use ManagesFields;

    /** @var ?Closure(CustomFieldSection): ?Closure */
    private static ?Closure $uniqueRuleModifierResolver = null;

    public string $entityType;

    public CustomFieldSection $section;

    public static function resolveUniqueRuleModifierUsing(?Closure $callback): void
    {
        self::$uniqueRuleModifierResolver = $callback;
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

    public function actions(): ?ActionGroup
    {
        if ($this->section->hasSystemDefinedFields()) {
            return null;
        }

        return ActionGroup::make([
            $this->editAction(),
            $this->activateAction(),
            $this->deactivateAction(),
            $this->deleteAction(),
        ])
            ->dropdownPlacement('bottom-end');
    }

    public function editAction(): Action
    {
        $sectionForm = SectionForm::entityType($this->entityType);

        if (self::$uniqueRuleModifierResolver instanceof Closure) {
            $modifier = (self::$uniqueRuleModifierResolver)($this->section);

            if ($modifier) {
                $sectionForm->modifyUniqueRuleUsing($modifier);
            }
        }

        return Action::make('edit')
            ->icon('heroicon-o-pencil-square')
            ->model(CustomFields::sectionModel())
            ->slideOver(FeatureManager::isEnabled(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY))
            ->record($this->section)
            ->schema($sectionForm->schema())
            ->fillForm($this->section->toArray())
            ->action(fn (array $data): bool => ! $this->section->hasSystemDefinedFields() && $this->section->update($data))
            ->visible(fn (CustomFieldSection $record): bool => ! $record->hasSystemDefinedFields())
            ->modalWidth(FeatureManager::isEnabled(CustomFieldsFeature::SECTION_CONDITIONAL_VISIBILITY) ? Width::ScreenLarge : Width::TwoExtraLarge);
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
            ->visible(fn (CustomFieldSection $record): bool => $record->isActive() && ! $record->hasSystemDefinedFields())
            ->action(fn (): bool => ! $this->section->hasSystemDefinedFields() && $this->section->deactivate());
    }

    public function deleteAction(): Action
    {
        return Action::make('delete')
            ->requiresConfirmation()
            ->icon('heroicon-o-trash')
            ->model(CustomFields::sectionModel())
            ->defaultColor('danger')
            ->record($this->section)
            ->visible(fn (CustomFieldSection $record): bool => ! $record->isActive() && ! $record->isSystemDefined())
            ->disabled(fn (CustomFieldSection $record): bool => $record->hasSystemDefinedFields())
            ->tooltip(fn (CustomFieldSection $record): string => $record->hasSystemDefinedFields()
                    ? __('custom-fields::custom-fields.section.form.contains_system_fields_cannot_delete')
                    : ''
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
            ->fillForm(['entity_type' => $this->entityType])
            ->mutateDataUsing(fn (array $data): array => $this->mutateFieldData($data, $this->entityType, $this->section->getKey()))
            ->action(fn (array $data) => $this->storeField($data))
            ->modalWidth(Width::ScreenLarge)
            ->slideOver();
    }

    public function render(): View
    {
        return view('custom-fields::livewire.manage-custom-field-section');
    }
}
