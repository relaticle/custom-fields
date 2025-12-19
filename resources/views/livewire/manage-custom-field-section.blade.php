<x-filament::section
        :after-header="$this->actions()"
        x-sortable-item="{{ $section->id }}"
        id="{{ $section->id }}"
        compact>

    <x-slot name="heading">
        <div class="flex justify-between">
            <div class="flex items-center gap-x-1">
                <x-filament::icon-button
                        icon="heroicon-m-bars-4"
                        color="gray"
                        x-sortable-handle
                />

                {{$section->name }}

                @if(!$section->isActive())
                    <x-filament::badge color="warning" size="sm">
                        {{ __('custom-fields::custom-fields.common.inactive') }}
                    </x-filament::badge>
                @endif
            </div>
        </div>
    </x-slot>


    <div
            x-sortable
            x-sortable-group="fields"
            data-section-id="{{ $section->id }}"
            default="12"
            class="fi-sc  fi-sc-has-gap fi-grid lg:fi-grid-cols"
            style="--cols-lg: repeat(12, minmax(0, 12fr)); --cols-default: repeat(2, minmax(0, 1fr));"
            @end.stop="$wire.updateFieldsOrder($event.to.getAttribute('data-section-id'), $event.to.sortable.toArray())"
    >
        @foreach ($this->fields as $field)
            @livewire('manage-custom-field', ['field' => $field], key($field->id . $field->width->value . str()->random(16)))
        @endforeach

        @if(!count($this->fields))
            <div class="fi-grid-col" style="--col-span-default: span 12 / span 12;">
                <div class="fi-ta-empty-state py-12">
                    <div class="fi-ta-empty-state-content mx-auto grid max-w-xs justify-items-center text-center">
                        <div class="fi-ta-empty-state-icon-ctn mb-4 rounded-full bg-gray-50 p-3 dark:bg-gray-800/50">
                            <x-filament::icon
                                icon="{{ __('custom-fields::custom-fields.empty_states.fields.icon') }}"
                                class="fi-ta-empty-state-icon h-6 w-6 text-gray-400 dark:text-gray-500"
                            />
                        </div>

                        <h4 class="fi-ta-empty-state-heading text-sm font-medium leading-5 text-gray-700 dark:text-gray-300 mb-1">
                            {{ __('custom-fields::custom-fields.empty_states.fields.heading') }}
                        </h4>

                        <p class="fi-ta-empty-state-description text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                            {{ __('custom-fields::custom-fields.empty_states.fields.description') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="pt-4">
        {{ $this->createFieldAction() }}
    </div>

    <x-filament-actions::modals/>

</x-filament::section>
