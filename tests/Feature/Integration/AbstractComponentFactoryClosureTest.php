<?php

declare(strict_types=1);

use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Relaticle\CustomFields\Contracts\FormComponentInterface;
use Relaticle\CustomFields\Facades\CustomFieldsType;
use Relaticle\CustomFields\FieldTypeSystem\BaseFieldType;
use Relaticle\CustomFields\FieldTypeSystem\FieldSchema;
use Relaticle\CustomFields\Filament\Integration\Factories\AbstractComponentFactory;
use Relaticle\CustomFields\Models\CustomField;
use Relaticle\CustomFields\Models\CustomFieldSection;
use Relaticle\CustomFields\Tests\Fixtures\Models\Post;

class ClosureProbeFactory extends AbstractComponentFactory
{
    public function probe(CustomField $customField): object
    {
        return $this->createComponent($customField, 'form_component', FormComponentInterface::class);
    }
}

class ClosureFormComponentFieldType extends BaseFieldType
{
    public function configure(): FieldSchema
    {
        return FieldSchema::text()
            ->key('closure-probe-type')
            ->label('Closure Probe Type')
            ->icon('heroicon-o-pencil')
            ->formComponent(fn (CustomField $customField): Field => TextInput::make($customField->getFieldName()));
    }
}

it('raises a clear error instead of a TypeError when createComponent() receives a Closure', function (): void {
    CustomFieldsType::register([
        'closure-probe-type' => ClosureFormComponentFieldType::class,
    ]);

    $section = CustomFieldSection::factory()->create([
        'name' => 'Closure Probe Section',
        'entity_type' => Post::class,
        'active' => true,
    ]);

    $field = CustomField::factory()->create([
        'custom_field_section_id' => $section->id,
        'name' => 'Closure Field',
        'code' => 'closure_field',
        'type' => 'closure-probe-type',
    ]);

    $factory = app(ClosureProbeFactory::class);

    expect(fn () => $factory->probe($field))->toThrow(InvalidArgumentException::class, 'resolved to a Closure');
});
