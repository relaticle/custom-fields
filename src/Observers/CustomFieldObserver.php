<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Observers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Relaticle\CustomFields\CustomFields;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Models\Scopes\TenantScope;
use Relaticle\CustomFields\Services\Relationships\DeleteRelationshipDefinition;
use Relaticle\CustomFields\Services\Visibility\BackendVisibilityService;
use Relaticle\CustomFields\Support\RelationshipTables;
use RuntimeException;

final class CustomFieldObserver
{
    /**
     * Prevent modification of protected attributes on system-defined fields.
     *
     * A type change is allowed only when the old and new types share the same storage
     * representation (e.g. select to status): the row keeps its options and values, only
     * how they are interpreted changes.
     */
    public function updating(CustomField $customField): void
    {
        if (! $customField->getOriginal('system_defined')) {
            return;
        }

        if ($customField->isDirty(['name', 'code'])) {
            throw new RuntimeException('Cannot modify name, code, or type of system-defined fields.');
        }

        if ($customField->isDirty('type') && ! $this->isStorageCompatibleTypeChange($customField)) {
            throw new RuntimeException('Cannot modify name, code, or type of system-defined fields.');
        }
    }

    private function isStorageCompatibleTypeChange(CustomField $customField): bool
    {
        $originalDataType = CustomFieldsType::getFieldType($customField->getOriginal('type'))?->dataType;
        $newDataType = CustomFieldsType::getFieldType($customField->type)?->dataType;

        return $originalDataType !== null && $originalDataType === $newDataType;
    }

    /**
     * Clear field cache after create or update.
     */
    public function saved(CustomField $customField): void
    {
        BackendVisibilityService::clearCache($customField->entity_type);
    }

    /**
     * The slots are read while the field row still exists: a host with foreign keys on has
     * already had them set to null by the time the delete lands.
     */
    public function deleting(CustomField $customField): void
    {
        $this->unpairRelationshipSlots($customField);
    }

    public function deleted(CustomField $customField): void
    {
        BackendVisibilityService::clearCache($customField->entity_type);

        // Delete the custom field options
        $customField->options()->delete();

        // Delete the custom field values
        $customField->values()->delete();
    }

    /**
     * Losing one presentation slot leaves the definition and its edges intact: the partner
     * keeps reading them from its own side. The lookup drops the tenant scope because the
     * field is already identified, and a foreign context must not strand a slot.
     */
    private function unpairRelationshipSlots(CustomField $customField): void
    {
        if (! RelationshipTables::exist()) {
            return;
        }

        DB::transaction(function () use ($customField): void {
            $definitions = CustomFields::newRelationshipModel()
                ->newQuery()
                ->withoutGlobalScope(TenantScope::class)
                ->where(fn (Builder $query): Builder => $query
                    ->where('from_field_id', $customField->getKey())
                    ->orWhere('to_field_id', $customField->getKey()))
                ->get();

            foreach ($definitions as $definition) {
                $this->unpair($definition, $customField);
            }
        });
    }

    /**
     * A definition that keeps no slot at all was never the headless kind, which is created
     * without fields, so it leaves with the field that was its last face.
     */
    private function unpair(CustomFieldRelationship $definition, CustomField $customField): void
    {
        $key = (string) $customField->getKey();

        $fromFieldId = (string) $definition->from_field_id === $key ? null : $definition->from_field_id;
        $toFieldId = (string) $definition->to_field_id === $key ? null : $definition->to_field_id;

        $definition->forceFill([
            'from_field_id' => $fromFieldId,
            'to_field_id' => $toFieldId,
        ])->save();

        if ($fromFieldId === null && $toFieldId === null) {
            app(DeleteRelationshipDefinition::class)->execute($definition);
        }
    }
}
