<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns;

use Filament\Tables\Columns\TextColumn as BaseTextColumn;
use Illuminate\Support\Str;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractTableColumn;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresColumnLabel;
use Relaticle\CustomFields\Filament\Integration\Concerns\Tables\ConfiguresSortable;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Relaticle\CustomFields\Models\CustomField;

/**
 * ABOUTME: Table column for rich text fields that strips HTML and truncates for display.
 * ABOUTME: Shows longer excerpt in tooltip when content exceeds display limit.
 */
final class RichTextColumn extends AbstractTableColumn
{
    use ConfiguresColumnLabel;
    use ConfiguresSortable;

    private const int DEFAULT_DISPLAY_LIMIT = 100;

    private const int DEFAULT_TOOLTIP_LIMIT = 500;

    public function make(CustomField $customField): BaseTextColumn
    {
        $column = BaseTextColumn::make($customField->getFieldName());

        $this->configureLabel($column, $customField);
        $this->configureSortable($column, $customField);

        $column->getStateUsing(function (HasCustomFields $record) use ($customField): ?string {
            $value = $record->getCustomFieldValue($customField);

            return $this->formatForDisplay($value);
        });

        $column->tooltip(function (HasCustomFields $record) use ($customField): ?string {
            $value = $record->getCustomFieldValue($customField);

            return $this->formatForTooltip($value);
        });

        return $column;
    }

    private function formatForDisplay(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $plain = $this->stripHtml((string) $value);

        return Str::limit($plain, self::DEFAULT_DISPLAY_LIMIT);
    }

    private function formatForTooltip(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $plain = $this->stripHtml((string) $value);

        if (mb_strlen($plain) <= self::DEFAULT_DISPLAY_LIMIT) {
            return null;
        }

        return Str::limit($plain, self::DEFAULT_TOOLTIP_LIMIT);
    }

    private function stripHtml(string $value): string
    {
        $plain = (string) preg_replace('/<\/(p|div|li|h[1-6]|br|tr)>/i', '</$1> ', $value);

        $plain = strip_tags($plain);

        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $plain = (string) preg_replace('/\s+/', ' ', $plain);

        return trim($plain);
    }
}
