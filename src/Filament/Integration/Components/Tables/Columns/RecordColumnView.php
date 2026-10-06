<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Closure;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Filament\Integration\Support\RecordChips;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Support\ThroughRelationResolver;
use Relaticle\CustomFields\Support\ViewFlavor;

/**
 * Custom Filament column that renders records using a blade view.
 */
final class RecordColumnView extends Column
{
    protected string $view = 'custom-fields::tables.columns.record-column';

    private ?CustomField $customField = null;

    private bool $multiple = false;

    private ?EntityConfigurationData $entity = null;

    private ?string $through = null;

    /** @var (Closure(Model): bool)|null */
    private ?Closure $shouldRenderFor = null;

    /**
     * The view renders from the record rather than the column state, so a through path has
     * to reach it here as well.
     */
    public function through(?string $relation): static
    {
        $this->through = $relation;

        return $this;
    }

    /**
     * A cell-level gate. Filament evaluates a column's own visibility once per table, so a
     * per-record condition has to be answered where the cell is built.
     *
     * @param  (Closure(Model): bool)|null  $callback
     */
    public function renderFor(?Closure $callback): static
    {
        $this->shouldRenderFor = $callback;

        return $this;
    }

    public function customField(CustomField $customField): static
    {
        $this->customField = $customField;
        $entityType = $customField->targetEntityType();

        if ($entityType !== null) {
            $this->entity = Entities::getEntity($entityType);
            $this->multiple = $customField->allowsMultipleRecords();
        }

        return $this;
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    /**
     * Chips are what a paired relationship draws. A record column keeps the row of linked
     * names it has always drawn, in either flavor.
     */
    public function getChipsView(): ?string
    {
        return $this->customField?->supportsPairing() === true
            ? ViewFlavor::view(UiSurface::RecordChips)
            : null;
    }

    /**
     * @return array<int, array{id: string, name: string, avatarUrl: ?string, avatarShape: string, url: ?string, provenance: ?string}>
     */
    public function getRecords(Model $record): array
    {
        // The path is validated before the gate, so an unsupported one is reported whether or
        // not the cell would have rendered.
        $subject = $this->through === null
            ? $record
            : app(ThroughRelationResolver::class)->relatedRecord($record, $this->through);

        if ($this->shouldRenderFor instanceof Closure && ! ($this->shouldRenderFor)($record)) {
            return [];
        }

        if (! $subject instanceof HasCustomFields || ! $this->customField instanceof CustomField) {
            return [];
        }

        $value = $subject->getCustomFieldValue($this->customField);
        $recordIds = match (true) {
            $value === null => [],
            is_array($value) => $value,
            default => [$value],
        };

        $chips = app(RecordChips::class);

        return $chips->build($this->entity, $recordIds, $this->provenance($chips, $subject));
    }

    /**
     * A table page reads provenance from the edges it already loaded. Loading the actor here
     * would be a query per row, so the host eager loads outgoingLinks.createdBy and
     * incomingLinks.createdBy, or the chip says nothing about where the link came from.
     *
     * Both link relations have to be loaded, not either: the reader falls back to SQL as soon
     * as one relation it needs for the direction is missing, which is the per-row query this
     * guard exists to prevent.
     *
     * @return array<string, string>
     */
    private function provenance(RecordChips $chips, HasCustomFields $subject): array
    {
        if (! $subject instanceof Model || ! $this->customField instanceof CustomField) {
            return [];
        }

        if (! $subject->relationLoaded('outgoingLinks') || ! $subject->relationLoaded('incomingLinks')) {
            return [];
        }

        return $chips->provenance($subject, $this->customField);
    }
}
