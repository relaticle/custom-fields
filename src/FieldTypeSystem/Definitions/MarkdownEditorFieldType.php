<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\MarkdownEditorComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\HtmlEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\RichTextColumn;
use Relaticle\CustomFields\Validation\Capabilities\MaxLengthCapability;
use Relaticle\CustomFields\Validation\Capabilities\MinLengthCapability;

/**
 * ABOUTME: Field type definition for Markdown Editor fields
 * ABOUTME: Provides Markdown Editor functionality with appropriate validation rules
 */
final class MarkdownEditorFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('markdown-editor')
            ->label(__('custom-fields::custom-fields.field_types.markdown_editor'))
            ->icon('mdi-language-markdown')
            ->formComponent(MarkdownEditorComponent::class)
            ->tableColumn(RichTextColumn::class)
            ->infolistEntry(HtmlEntry::class)
            ->priority(85)
            ->withValidationCapabilities(
                MinLengthCapability::class,
                MaxLengthCapability::class,
            );
    }
}
