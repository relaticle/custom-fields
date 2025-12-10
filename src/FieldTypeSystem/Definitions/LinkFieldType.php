<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\LinkComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for Link fields
 * ABOUTME: Provides Link functionality with URL validation and multi-value support
 */
class LinkFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::multiChoice()
            ->key('link')
            ->label('Link')
            ->icon('mdi-link')
            ->formComponent(LinkComponent::class)
            ->tableColumn(TextColumn::class)
            ->infolistEntry(TextEntry::class)
            ->priority(60)
            ->supportsMultiValue()
            ->supportsUniqueConstraint()
            ->withArbitraryValues()
            ->withoutUserOptions()
            ->availableValidationRules([
                ValidationRule::REQUIRED,
                ValidationRule::URL,
                ValidationRule::STARTS_WITH,
                ValidationRule::MIN,
                ValidationRule::MAX,
            ]);
    }
}
