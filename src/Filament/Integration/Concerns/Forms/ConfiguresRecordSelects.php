<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Concerns\Forms;

use Relaticle\CustomFields\Filament\Integration\Components\Forms\RecordSelectInput\RecordSelectInputComponent;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldRelationship;
use Relaticle\CustomFields\Services\Relationships\MissingRelationshipDefinitions;

/**
 * What both link types configure the same way: where the input looks, how many records it
 * takes, and what it does when the field points nowhere.
 */
trait ConfiguresRecordSelects
{
    private const int MAX_MULTIPLE_RECORDS = 100;

    /**
     * @template TComponent of RecordSelectInputComponent
     *
     * @param  TComponent  $component
     * @return TComponent
     */
    protected function configureRecordSelect(RecordSelectInputComponent $component, CustomField $customField): RecordSelectInputComponent
    {
        $definition = $customField->relationshipDefinition();
        $allowMultiple = $customField->allowsMultipleRecords();
        $maxValues = $allowMultiple ? self::MAX_MULTIPLE_RECORDS : 1;

        $component
            ->customField($customField)
            ->lookupType($customField->targetEntityType())
            ->allowMultiple($allowMultiple)
            ->maxValues($maxValues)
            ->placeholder(__('custom-fields::custom-fields.record.search_placeholder'))
            ->addLabel(__('custom-fields::custom-fields.record.add_record_placeholder'))
            ->rules($this->recordValueRules($definition, $maxValues));

        // A hidden field is never dehydrated, so a form that cannot show the field cannot
        // write it either, which is what a missing definition should mean on a write path.
        if (! $definition instanceof CustomFieldRelationship) {
            app(MissingRelationshipDefinitions::class)->report($customField);

            $component->hidden();
        }

        return $component;
    }

    /**
     * Cardinality caps a relationship slot, and says so in words the user can act on, so a
     * count rule beside it would report one mistake twice. A field with no definition still
     * needs one.
     *
     * @return array<int, string>
     */
    private function recordValueRules(?CustomFieldRelationship $definition, int $maxValues): array
    {
        return $definition instanceof CustomFieldRelationship
            ? ['array']
            : ['array', 'max:'.$maxValues];
    }
}
