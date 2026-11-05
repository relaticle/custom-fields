@php
    $choices = $getTypeChoices();
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @include('custom-fields::flavors.polished.partials.type-picker-grid', [
        'choices' => $choices,
        'isDisabled' => $isDisabled,
        'label' => $getLabel(),
        'stateBinding' => $applyStateBindingModifiers("\$entangle('{$statePath}')"),
    ])
</x-dynamic-component>
