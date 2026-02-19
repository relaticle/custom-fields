<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\FieldTypeSystem\Definitions;

use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\FileUploadComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Infolists\TextEntry;
use Relaticle\CustomFields\Filament\Integration\Components\Tables\Columns\TextColumn;
use Relaticle\CustomFields\Validation\Capabilities\AcceptedFileTypesCapability;
use Relaticle\CustomFields\Validation\Capabilities\MaxFileSizeCapability;

/**
 * ABOUTME: Field type definition for file upload fields
 * ABOUTME: Provides file upload functionality with type validation and storage management
 */
class FileUploadFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::string()
            ->key('file-upload')
            ->label('File Upload')
            ->icon('heroicon-o-paper-clip')
            ->formComponent(FileUploadComponent::class)
            ->tableColumn(TextColumn::class)
            ->infolistEntry(TextEntry::class)
            ->priority(17)
            ->searchable()
            ->defaultValidationRules(['file'])
            ->withValidationCapabilities(
                AcceptedFileTypesCapability::class,
                MaxFileSizeCapability::class,
            );
    }
}
