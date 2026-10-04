<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Illuminate\Support\Str;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\LinkComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\LinkEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\LinkColumn;
use Relaticle\CustomFields\Models\CustomField;

class LinkFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::multiChoice()
            ->key('link')
            ->label(__('custom-fields::custom-fields.field_types.link'))
            ->icon('mdi-link')
            ->formComponent(LinkComponent::class)
            ->tableColumn(LinkColumn::class)
            ->infolistEntry(LinkEntry::class)
            ->priority(60)
            ->supportsMultiValue()
            ->supportsUniqueConstraint()
            ->withArbitraryValues()
            ->withoutUserOptions()
            ->defaultItemValidationRules(['max:2048', 'regex:/^(https?:\/\/)?([a-zA-Z0-9]([a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}(\/.*)?$/']);
    }

    public function setValue(string $value): string
    {
        return preg_replace('#^https?://#i', '', trim($value));
    }

    public function normalize(string $value, CustomField $customField): string
    {
        if ($customField->setting('link_variant') !== 'domain') {
            return $this->setValue($value);
        }

        $host = (string) Str::of($value)
            ->lower()
            ->replaceMatches('#[\s\x{00A0}\x{200B}\x{FEFF}\x{3000}]+#u', '')
            ->replaceMatches('#^[a-z][a-z0-9+.-]*://#', '')
            ->before('/')
            ->before('?')
            ->before('#')
            ->replaceMatches('#^.*@#', '')
            ->before(':')
            ->replaceMatches('#^(www\.)+#', '')
            ->rtrim('.');

        if ($host === '' || str_contains($host, '.')) {
            return $host;
        }

        $unwrapped = (string) preg_replace('#^(?:https?://)+#i', '', trim($value));

        return $unwrapped === $value ? $value : $this->normalize($unwrapped, $customField);
    }
}
