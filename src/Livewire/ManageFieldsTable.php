<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Livewire;

use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\Size;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\View as ViewFactory;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Filament\Management\Schemas\FieldForm;
use Relaticle\CustomFields\Livewire\Concerns\ManagesCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Support\FieldFormConfiguration;
use Relaticle\CustomFields\Support\RelationshipTables;
use Relaticle\CustomFields\Support\ViewFlavor;

/**
 * Livewire component for managing custom fields in a flat table layout.
 *
 * Shows ALL fields for the entity type with search, reordering, and inline editing.
 * Used when sections are disabled (SYSTEM_SECTIONS = false).
 */
final class ManageFieldsTable extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;
    use ManagesCustomFields;

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

    /**
     * The relationship each paired field in this table belongs to, resolved in a fixed number
     * of queries (the definitions plus one eager load per slot) rather than once per row. The
     * partner is read through the relation so it keeps the tenant and activable scopes a
     * hand-rolled subselect would drop. A pair whose other end is on this same entity carries
     * the partner id, which is what connects the two rows.
     *
     * @return array<int|string, array{definition: string, partner_id: ?string, partner_name: ?string, entity: ?string, symmetric: bool}>
     */
    #[Computed]
    public function relationshipPairs(): array
    {
        if (! RelationshipTables::exist()) {
            return [];
        }

        $fields = $this->activeFields()
            ->concat($this->inactiveFields())
            ->filter(fn (CustomField $field): bool => $field->supportsPairing())
            ->keyBy(fn (CustomField $field): string => (string) $field->getKey());

        if ($fields->isEmpty()) {
            return [];
        }

        $keys = $fields->map(fn (CustomField $field): int|string => $field->getKey())->values()->all();

        $definitions = CustomFields::newRelationshipModel()
            ->newQuery()
            ->with(['fromField', 'toField'])
            ->where(function (Builder $query) use ($keys): void {
                $query->whereIn('from_field_id', $keys)->orWhereIn('to_field_id', $keys);
            })
            ->get();

        $pairs = [];

        foreach ($definitions as $definition) {
            $ends = [
                [$definition->from_field_id, $definition->toField, $definition->to_entity_type],
                [$definition->to_field_id, $definition->fromField, $definition->from_entity_type],
            ];

            foreach ($ends as [$fieldId, $partner, $entityType]) {
                if ($fieldId === null || ! $fields->has((string) $fieldId)) {
                    continue;
                }

                $partnerIsVisible = ! $definition->is_symmetric
                    && $partner instanceof CustomField
                    && $fields->has((string) $partner->getKey());

                $pairs[(string) $fieldId] = [
                    'definition' => (string) $definition->getKey(),
                    'partner_id' => $partnerIsVisible ? (string) $partner->getKey() : null,
                    'partner_name' => $definition->is_symmetric ? null : $partner?->name,
                    'entity' => Entities::getEntity($entityType)?->getLabelSingular(),
                    'symmetric' => $definition->is_symmetric,
                ];
            }
        }

        return $pairs;
    }

    /**
     * @return Builder<CustomField>
     */
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
        unset($this->activeFields, $this->inactiveFields, $this->relationshipPairs);
    }

    /**
     * @param  array<int, int|string>  $order
     */
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
            ->modalHeading(__('custom-fields::custom-fields.field.actions.edit_modal_heading'))
            ->icon('heroicon-o-pencil-square')
            ->model(CustomFields::customFieldModel())
            ->record(fn (array $arguments): ?CustomField => $this->findField($arguments['fieldId']))
            ->schema(FieldForm::schema(withOptionsRelationship: true))
            ->fillForm(fn (CustomField $record): array => $this->fieldFormState($record))
            ->action(function (array $data, CustomField $record): void {
                $this->updateField($record, $data);
                $this->resetFieldsCache();
            })
            ->modalWidth(FieldFormConfiguration::width())
            ->extraModalWindowAttributes($this->submitsOnMetaEnter())
            ->slideOver(FieldFormConfiguration::isSlideOver());
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
            ->modalHeading(__('custom-fields::custom-fields.field.actions.delete_modal_heading'))
            ->modalDescription(__('custom-fields::custom-fields.field.actions.delete_modal_description'))
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
            ->schema(FieldForm::schema(withOptionsRelationship: false, entityType: $this->entityType))
            ->mutateDataUsing(fn (array $data): array => $this->mutateFieldData($data, $this->entityType))
            ->action(function (array $data): void {
                $this->storeField($data);
                $this->resetFieldsCache();
            })
            ->modalWidth(FieldFormConfiguration::width())
            ->extraModalWindowAttributes($this->submitsOnMetaEnter())
            ->slideOver(FieldFormConfiguration::isSlideOver());
    }

    public function render(): View
    {
        return ViewFactory::make(
            ViewFlavor::view(UiSurface::AttributeTable) ?? 'custom-fields::livewire.manage-fields-table'
        );
    }
}
