<?php

declare(strict_types=1);

namespace Relaticle\CustomFields\Filament\Integration\Factories;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Relaticle\CustomFields\Contracts\FormComponentInterface;
use Relaticle\CustomFields\Filament\Integration\Base\AbstractFormComponent;
use Relaticle\CustomFields\Filament\Integration\Components\Forms\ClosureFormAdapter;
use Relaticle\CustomFields\Models\CustomField;

/**
 * @extends AbstractComponentFactory<FormComponentInterface, Field>
 */
final class FieldComponentFactory extends AbstractComponentFactory
{
    /**
     * @param  array<string>  $dependentFieldCodes
     * @param  Collection<int, CustomField>|null  $allFields
     *
     * @throws BindingResolutionException
     */
    public function create(CustomField $customField, array $dependentFieldCodes = [], ?Collection $allFields = null, ?Model $record = null): Field
    {
        $formComponentDefinition = $customField->typeData->formComponent;

        // Handle inline component (Closure) - use ClosureFormAdapter for full AbstractFormComponent benefits
        if ($formComponentDefinition instanceof Closure) {
            /** @var AbstractFormComponent $component */
            $component = $this->container->make(ClosureFormAdapter::class, [
                'closure' => $formComponentDefinition,
            ]);
        } else {
            // Handle traditional component class
            /** @var FormComponentInterface $component */
            $component = $this->createComponent($customField, 'form_component', FormComponentInterface::class);
        }

        // Only AbstractFormComponent consumes the optional $record (server-side relation-attribute
        // visibility). Third-party FormComponentInterface implementers keep the original 3-arg contract,
        // so the package stays backward compatible for that public extension point.
        if ($component instanceof AbstractFormComponent) {
            return $component->make($customField, $dependentFieldCodes, $allFields, $record);
        }

        return $component->make($customField, $dependentFieldCodes, $allFields);
    }
}
