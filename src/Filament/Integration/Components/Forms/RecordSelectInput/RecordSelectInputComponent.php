<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms\RecordSelectInput;

use Filament\Forms\Components\Contracts\HasNestedRecursiveValidationRules as HasNestedRecursiveValidationRulesContract;
use Filament\Forms\Components\Field;
use Relaticle\CustomFields\Filament\Integration\Concerns\Forms\SelectsRecords;

/**
 * The one-way record field's input: a searchable select with avatars, showing one record or a
 * row of removable pills. It is the same control in every flavor, because a flavor decides how
 * a surface looks and this type has only ever had the one look.
 *
 * The paired type's picker shares the select through SelectsRecords and adds chips, the inline
 * move confirmation, and provenance on hover.
 */
final class RecordSelectInputComponent extends Field implements HasNestedRecursiveValidationRulesContract
{
    use SelectsRecords;

    protected string $view = 'custom-fields::forms.record-select-input';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpRecordSelect();
    }

    /**
     * A plain select draws no chip, so it says nothing about where a link came from.
     *
     * @return array<string, string>
     */
    private function provenance(): array
    {
        return [];
    }

    /**
     * Whether taking a record could take it away from another holder. The record type never
     * offers that move, so its select never asks.
     */
    public function checksHolderConflicts(): bool
    {
        return false;
    }
}
