<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\Enums\ValidationRule;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\MarkdownEditorComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\HtmlEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;

/**
 * ABOUTME: Field type definition for Markdown Editor fields
 * ABOUTME: Provides Markdown Editor functionality with appropriate validation rules
 */
class MarkdownEditorFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('markdown-editor')
            ->label('Markdown Editor')
            ->icon('mdi-language-markdown')
            ->formComponent(MarkdownEditorComponent::class)
            ->tableColumn(TextColumn::class)
            ->infolistEntry(HtmlEntry::class)
            ->priority(85)
            ->availableValidationRules([
                ValidationRule::REQUIRED,
                ValidationRule::MIN,
                ValidationRule::MAX,
                ValidationRule::REGEX,
            ]);
    }
}
