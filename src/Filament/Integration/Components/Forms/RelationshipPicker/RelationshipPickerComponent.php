<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms\RelationshipPicker;

use Filament\Forms\Components\Contracts\HasNestedRecursiveValidationRules as HasNestedRecursiveValidationRulesContract;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Renderless;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\SelectsRecords;
use Relaticle\CustomFields\Filament\Integration\Support\RecordChips;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\CardinalityGuard;
use Relaticle\CustomFields\Support\ViewFlavor;

/**
 * The paired relationship's input: the record select plus everything pairing adds. Chips the
 * user can unlink, an inline confirmation before a record is taken from its holder, a page to
 * create the record it could not find, and where each existing link came from.
 */
final class RelationshipPickerComponent extends Field implements HasNestedRecursiveValidationRulesContract
{
    use SelectsRecords;

    protected string $view = 'custom-fields::forms.relationship-picker';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRecordSelect();

        $polishedView = ViewFlavor::view(UiSurface::RecordPicker);

        if ($polishedView !== null) {
            $this->view($polishedView);
        }
    }

    /**
     * Where each selected link came from, for the chip's hover. Candidates in the dropdown are
     * not linked yet, so only the selected records carry it.
     *
     * @return array<string, string>
     */
    private function provenance(): array
    {
        $customField = $this->getCustomField();

        // A component built outside a schema, as the import path does, has no record to read
        // provenance from and asking for one would fail before it could say so.
        if (! $customField instanceof CustomField || ! isset($this->container)) {
            return [];
        }

        $record = $this->getRecord();

        if (! $record instanceof Model) {
            return [];
        }

        $record->loadMissing(['outgoingLinks.createdBy', 'incomingLinks.createdBy']);

        return app(RecordChips::class)->provenance($record, $customField);
    }

    /**
     * The page that creates a record of the target entity, or null when the host registered no
     * resource with one: the picker offers create-new only where it can land somewhere.
     */
    public function getCreateUrl(): ?string
    {
        $entity = $this->getEntityConfiguration();

        return $entity instanceof EntityConfigurationData
            ? app(RecordChips::class)->createUrl($entity)
            : null;
    }

    public function getCreateLabel(): ?string
    {
        $entity = $this->getEntityConfiguration();

        if (! $entity instanceof EntityConfigurationData) {
            return null;
        }

        $label = __('custom-fields::custom-fields.record.create_new', ['entity' => $entity->getLabelSingular()]);

        return is_string($label) ? $label : null;
    }

    /**
     * Whether taking a record could take it away from another holder. A relationship whose far
     * end holds many never can, so the picker skips the round trip that asks.
     */
    public function checksHolderConflicts(): bool
    {
        $customField = $this->getCustomField();
        $definition = $customField?->relationshipDefinition();

        if (! $customField instanceof CustomField || ! $definition instanceof CustomFieldRelationship) {
            return false;
        }

        return app(CardinalityGuard::class)->endHoldsOne(
            $definition,
            $definition->writeEnd($customField)->side->opposite(),
        );
    }

    /**
     * What the writer would refuse for one candidate, so the picker can confirm the move
     * inline instead of failing on save. The guard is the single source of that sentence.
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function holderConflictFor(string $recordId): ?string
    {
        $customField = $this->getCustomField();
        $definition = $customField?->relationshipDefinition();

        if (! $customField instanceof CustomField || ! $definition instanceof CustomFieldRelationship) {
            return null;
        }

        $violations = app(CardinalityGuard::class)->violations(
            $definition,
            $definition->writeEnd($customField),
            ($record = $this->getRecord()) instanceof Model ? $record->getKey() : null,
            [$recordId],
        );

        return $violations[0] ?? null;
    }
}
