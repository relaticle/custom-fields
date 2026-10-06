<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Management\Forms\Components;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Str;
use Relaticle\CustomFields\Data\EntityConfigurationData;
use Relaticle\CustomFields\Enums\RelationshipCardinality;
use Relaticle\CustomFields\Enums\UiSurface;
use Relaticle\CustomFields\Facades\Entities;
use Relaticle\CustomFields\Support\ViewFlavor;

/**
 * The polished presentation of the record configuration: the same child components the stock
 * fieldset holds, laid out as two entity cards with the cardinality between them.
 */
final class RelationshipConfigurator extends Component
{
    public static function make(): static
    {
        $static = app(self::class);
        $static->configure();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $polishedView = ViewFlavor::view(UiSurface::RelationshipConfigurator);

        if ($polishedView !== null) {
            $this->view($polishedView);
        }

        $this->columnSpanFull();
    }

    /**
     * The children by name, so the view places each one instead of counting on an order. A
     * component the shared closures hide is absent here too, which is how the flavor stays
     * presentation: visibility is decided once, in PHP, for both flavors.
     *
     * @return array<string, Field>
     */
    public function getConfiguredFields(): array
    {
        $fields = [];

        foreach ($this->getChildSchema()?->getComponents() ?? [] as $component) {
            if ($component instanceof Field) {
                $fields[$component->getName()] = $component;
            }
        }

        return $fields;
    }

    public function getSourceEntity(): ?EntityConfigurationData
    {
        return $this->entity($this->stateAt('entity_type'));
    }

    public function getTargetEntity(): ?EntityConfigurationData
    {
        return $this->entity($this->stateAt('relationship.target_entity_type'));
    }

    /**
     * The cardinality in words, with both entity names in it: the control between the two
     * cards has to read as the sentence it is choosing.
     */
    public function getCardinalitySentence(): ?string
    {
        $cardinality = RelationshipCardinality::tryFrom((string) $this->stateAt('relationship.cardinality'));
        $source = $this->getSourceEntity();
        $target = $this->getTargetEntity();

        if (! $cardinality instanceof RelationshipCardinality || ! $source instanceof EntityConfigurationData || ! $target instanceof EntityConfigurationData) {
            return null;
        }

        // The count in front of each name is the case's own name read left to right, not the
        // end constraint that shares the word: many_to_one holds one record per source.
        [$sourceIsPlural, $targetIsPlural] = match ($cardinality) {
            RelationshipCardinality::OneToOne => [false, false],
            RelationshipCardinality::OneToMany => [false, true],
            RelationshipCardinality::ManyToOne => [true, false],
            RelationshipCardinality::ManyToMany => [true, true],
        };

        // A host resource label can be lowercase for Filament's sentence use, and both names
        // sit mid-sentence here only after a count word that opens it.
        return __('custom-fields::custom-fields.field.form.record.sentence.'.$cardinality->value, [
            'source' => Str::ucfirst($sourceIsPlural ? $source->getLabelPlural() : $source->getLabelSingular()),
            'target' => Str::ucfirst($targetIsPlural ? $target->getLabelPlural() : $target->getLabelSingular()),
        ]);
    }

    /**
     * The name input belongs to every field type, so it stays in the shared grid above; the
     * source card mirrors it live rather than holding a second input over the same state.
     */
    public function getFieldNameStatePath(): string
    {
        return $this->resolveRelativeStatePath('name');
    }

    public function getFieldName(): string
    {
        $name = $this->stateAt('name');

        return is_string($name) && trim($name) !== ''
            ? $name
            : __('custom-fields::custom-fields.field.form.record.untitled_field');
    }

    public function isSymmetric(): bool
    {
        return $this->stateAt('relationship.is_symmetric') === true;
    }

    public function pairsAField(): bool
    {
        return ! $this->isSymmetric()
            && filled($this->stateAt('relationship.paired_field_name'));
    }

    private function entity(mixed $entityType): ?EntityConfigurationData
    {
        if (! is_string($entityType) || $entityType === '') {
            return null;
        }

        return Entities::getEntity($entityType);
    }

    private function stateAt(string $path): mixed
    {
        return $this->evaluate(fn (Get $get): mixed => $get($path));
    }
}
