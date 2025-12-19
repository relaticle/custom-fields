<x-filament-panels::page>
    <x-filament::tabs label="Content tabs" contained>
        @foreach ($this->entityTypes as $key => $label)
            <x-filament::tabs.item active="{{ $key === $this->currentEntityType }}"
                                   wire:click="setCurrentEntityType('{{ addslashes($key) }}')">
                {{ $label }}
            </x-filament::tabs.item>
        @endforeach
    </x-filament::tabs>

    <div class="custom-fields-component">
        <div
            x-sortable
            wire:end.stop="updateSectionsOrder($event.target.sortable.toArray())"
            class="flex flex-col gap-y-6"
        >
            @foreach ($this->sections as $section)
                @livewire('manage-custom-field-section', ['entityType' => $this->currentEntityType, 'section' => $section], key($section->id . str()->random(16)))
            @endforeach

            @if(!count($this->sections))
                <div class="fi-ta-empty-state px-6 py-16">
                    <div class="fi-ta-empty-state-content mx-auto grid max-w-md justify-items-center text-center">
                        <div class="fi-ta-empty-state-icon-ctn mb-6 rounded-full bg-primary-50 p-4 dark:bg-primary-950/50">
                            <x-filament::icon
                                icon="{{ __('custom-fields::custom-fields.empty_states.sections.icon') }}"
                                class="fi-ta-empty-state-icon h-8 w-8 text-primary-500 dark:text-primary-400"
                            />
                        </div>

                        <h3 class="fi-ta-empty-state-heading text-lg font-semibold leading-7 text-gray-950 dark:text-white mb-2">
                            {{ __('custom-fields::custom-fields.empty_states.sections.heading') }}
                        </h3>

                        <p class="fi-ta-empty-state-description text-sm text-gray-600 dark:text-gray-400 mb-6 leading-relaxed">
                            {{ __('custom-fields::custom-fields.empty_states.sections.description') }}
                        </p>

                        <div class="fi-ta-empty-state-action">
                            {{ $this->createSectionAction }}
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-6 flex justify-center">
                    {{ $this->createSectionAction }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
