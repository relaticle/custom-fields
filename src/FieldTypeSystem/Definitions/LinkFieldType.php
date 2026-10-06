<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

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
        $value = trim($value);

        if (preg_match('#^https?://$#i', $value) === 1) {
            return '';
        }

        if (preg_match('#^((?:https?://)?[^\s/?\#]+\.[^\s/?\#]+)(.*)$#is', $value, $parts) !== 1) {
            return $value;
        }

        return (string) preg_replace('#/+$#', '', strtolower($parts[1]).$parts[2]);
    }

    /**
     * @return list<string>
     */
    public function equivalentValues(string $value, CustomField $customField): array
    {
        $stored = $this->setValue($value);
        $bare = $this->withoutScheme($stored);

        return array_values(array_unique([$this->normalize($value, $customField), $stored, $bare, "https://{$bare}", "http://{$bare}"]));
    }

    private function withoutScheme(string $value): string
    {
        return (string) preg_replace('#^https?://#i', '', $value);
    }
}
