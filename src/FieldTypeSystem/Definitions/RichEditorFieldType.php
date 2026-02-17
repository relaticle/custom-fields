<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\RichEditorComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\HtmlEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\RichTextColumn;

/**
 * ABOUTME: Field type definition for Rich Editor fields
 * ABOUTME: Provides Rich Editor functionality with appropriate validation rules
 */
final class RichEditorFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('rich-editor')
            ->label('Rich Editor')
            ->icon('mdi-format-text')
            ->formComponent(RichEditorComponent::class)
            ->tableColumn(RichTextColumn::class)
            ->infolistEntry(HtmlEntry::class)
            ->priority(80)
            ->canHaveMinLength()
            ->canHaveMaxLength()
            ->importExample('Sample rich text content')
            ->importTransformer(function (mixed $state): ?string {
                if (blank($state)) {
                    return null;
                }

                if (is_array($state)) {
                    return json_encode($state);
                }

                $text = (string) $state;

                if (str_starts_with(trim($text), '<')) {
                    return $text;
                }

                $lines = preg_split('/\r\n|\r|\n/', $text);
                $paragraphs = array_map(fn (string $line): string => '<p>'.e($line).'</p>', $lines);

                return implode('', $paragraphs);
            });
    }
}
