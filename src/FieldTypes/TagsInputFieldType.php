<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypes;

use Relaticle\CustomFields\Contracts\FieldTypeDefinitionInterface;
use Relaticle\CustomFields\Enums\FieldDataType;
use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypes\Concerns\HasCommonFieldProperties;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\TagsInputComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\MultiChoiceEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\MultiChoiceColumn;

/**
 * ABOUTME: Field type definition for Tags Input fields
 * ABOUTME: Provides Tags Input functionality with appropriate validation rules
 */
class TagsInputFieldType implements FieldTypeDefinitionInterface
{
    use HasCommonFieldProperties;

    public function getKey(): string
    {
        return 'tags-input';
    }

    public function getLabel(): string
    {
        return 'Tags Input';
    }

    public function getIcon(): string
    {
        return 'mdi-tag-multiple';
    }

    public function getDataType(): FieldDataType
    {
        return FieldDataType::MULTI_CHOICE;
    }

    public function getFormComponentClass(): string
    {
        return TagsInputComponent::class;
    }

    public function getTableColumnClass(): string
    {
        return MultiChoiceColumn::class;
    }

    public function getInfolistEntryClass(): string
    {
        return MultiChoiceEntry::class;
    }

    public function getPriority(): int
    {
        return 70;
    }

    public function allowedValidationRules(): array
    {
        return [
            ValidationRule::REQUIRED,
            ValidationRule::ARRAY,
            ValidationRule::MIN,
            ValidationRule::MAX,
            ValidationRule::DISTINCT,
        ];
    }
}
