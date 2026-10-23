<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Infolists;

use Filament\Infolists\Components\ViewEntry;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractInfolistEntry;
use Relaticle\CustomFields\Filament\Integration\Support\RecordChips;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\MissingRelationshipDefinitions;
use Relaticle\CustomFields\Support\ViewFlavor;

final class RecordEntry extends AbstractInfolistEntry
{
    public function make(CustomField $customField, ?Model $record = null): ViewEntry
    {
        $definition = $customField->relationshipDefinition();

        // The entry has nothing to read without a definition, so it leaves the infolist alone.
        if (! $definition instanceof CustomFieldRelationship) {
            app(MissingRelationshipDefinitions::class)->report($customField);

            return ViewEntry::make($customField->getFieldName())
                ->label($customField->name)
                ->view('custom-fields::infolists.record-entry')
                ->state(['records' => [], 'multiple' => false, 'chipsView' => null])
                ->hidden();
        }

        $entity = Entities::getEntity($definition->targetEntityTypeFor($customField));
        $isMultiSelect = $customField->allowsMultipleRecords();

        return ViewEntry::make($customField->getFieldName())
            ->label($customField->name)
            ->view('custom-fields::infolists.record-entry')
            ->state(function (HasCustomFields $record) use ($customField, $entity, $isMultiSelect): array {
                $value = $record->getCustomFieldValue($customField);
                $recordIds = match (true) {
                    $value === null => [],
                    is_array($value) => $value,
                    default => [$value],
                };

                $chips = app(RecordChips::class);

                return [
                    'records' => $chips->build($entity, $recordIds, $this->provenance($chips, $record, $customField)),
                    'multiple' => $isMultiSelect,
                    'chipsView' => $customField->supportsPairing() ? ViewFlavor::view(UiSurface::RecordChips) : null,
                ];
            });
    }

    /**
     * One record's page can afford the actor morph, which a table page cannot, so this is the
     * one surface that says who made the link rather than only when it was made.
     *
     * @return array<string, string>
     */
    private function provenance(RecordChips $chips, HasCustomFields $record, CustomField $customField): array
    {
        if (! $customField->supportsPairing() || ! $record instanceof Model) {
            return [];
        }

        $record->loadMissing(['outgoingLinks.createdBy', 'incomingLinks.createdBy']);

        return $chips->provenance($record, $customField);
    }
}
