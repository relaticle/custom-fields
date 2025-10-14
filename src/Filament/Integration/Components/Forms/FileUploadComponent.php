<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Components\Forms;

use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Models\CustomField;

final readonly class FileUploadComponent extends AbstractFormComponent
{
    public function create(CustomField $customField): Field
    {
        $defaults = $this->getSmartDefaults();

        $component = FileUpload::make($customField->getFieldName())
            ->placeholder('Choose a file or drag and drop');

        // Apply all settings dynamically using base class method
        return $this->applySettingsToComponent($component, $defaults);
    }

    protected function getSmartDefaults(): array
    {
        return [
            'disk' => 'public',
            'directory' => 'uploads/custom-fields',
            'visibility' => 'public',
            'maxSize' => 10240, // 10MB
            'multiple' => false,
            'maxFiles' => 1,
            'previewable' => true,
            'downloadable' => true,
            'openable' => true,
            'preserveFilenames' => false,
            'acceptedFileTypes' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'image/jpeg',
                'image/png',
            ],
        ];
    }
}
