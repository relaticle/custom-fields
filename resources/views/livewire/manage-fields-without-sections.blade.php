<div class="flex flex-col gap-y-6">
    @if(count($this->fields))
        <div
            x-sortable
            x-sortable-group="fields"
            class="fi-sc fi-sc-has-gap fi-grid lg:fi-grid-cols"
            style="--cols-lg: repeat(12, minmax(0, 12fr)); --cols-default: repeat(2, minmax(0, 1fr));"
            @end.stop="$wire.updateFieldsOrder($event.to.sortable.toArray())"
        >
            @foreach ($this->fields as $field)
                @livewire('manage-custom-field', ['field' => $field], key($field->id . $field->width->value . str()->random(16)))
            @endforeach
        </div>

        <div class="flex justify-center">
            {{ $this->createFieldAction() }}
        </div>
    @else
        <div class="px-6 py-16">
            <div class="mx-auto grid max-w-md justify-items-center text-center">
                <div class="fi-ta-empty-state-icon-ctn mb-6 rounded-full bg-primary-50 p-4 dark:bg-primary-950/50">
                    <x-filament::icon
                        icon="{{ __('custom-fields::custom-fields.empty_states.fields_no_sections.icon') }}"
                        class="fi-ta-empty-state-icon h-8 w-8 text-primary-500 dark:text-primary-400"
                    />
                </div>

                <h3 class="fi-ta-empty-state-heading text-lg font-semibold leading-7 text-gray-950 dark:text-white mb-2">
                    {{ __('custom-fields::custom-fields.empty_states.fields_no_sections.heading') }}
                </h3>

                <p class="fi-ta-empty-state-description text-sm text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                    {{ __('custom-fields::custom-fields.empty_states.fields_no_sections.description') }}
                </p>

                <div class="fi-ta-empty-state-action">
                    {{ $this->createFieldAction() }}
                </div>
            </div>
        </div>
    @endif

    <x-filament-actions::modals/>
</div>
