<div class="space-y-4">
    {{-- Header with search and create button --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex-1 max-w-sm">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('custom-fields::custom-fields.field.form.search_placeholder') }}"
                />
            </x-filament::input.wrapper>
        </div>

        {{ $this->createFieldAction() }}
    </div>

    {{-- Fields Table --}}
    <div class="fi-ta rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
        {{-- Responsive scroll container --}}
        <div class="overflow-x-auto">
            <div class="min-w-[600px]">
                {{-- Table Header --}}
                <div class="grid grid-cols-[40px_1fr_minmax(120px,160px)_minmax(100px,140px)_minmax(80px,120px)_50px] border-b border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                    <div></div>
                    <div class="flex items-center gap-2 px-3 py-3 text-sm font-semibold text-gray-950 dark:text-white">
                        <x-filament::icon icon="heroicon-o-tag" class="h-4 w-4 text-gray-400 shrink-0"/>
                        <span class="truncate">{{ __('custom-fields::custom-fields.field.form.name') }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-3 text-sm font-semibold text-gray-950 dark:text-white">
                        <x-filament::icon icon="heroicon-o-square-3-stack-3d" class="h-4 w-4 text-gray-400 shrink-0"/>
                        <span class="truncate">{{ __('custom-fields::custom-fields.field.form.type') }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-3 text-sm font-semibold text-gray-950 dark:text-white">
                        <x-filament::icon icon="heroicon-o-lock-closed" class="h-4 w-4 text-gray-400 shrink-0"/>
                        <span class="truncate">{{ __('custom-fields::custom-fields.common.constraints') }}</span>
                    </div>
                    <div class="flex items-center gap-2 px-3 py-3 text-sm font-semibold text-gray-950 dark:text-white">
                        <x-filament::icon icon="heroicon-o-adjustments-horizontal"
                                          class="h-4 w-4 text-gray-400 shrink-0"/>
                        <span class="truncate">{{ __('custom-fields::custom-fields.common.properties') }}</span>
                    </div>
                    <div></div>
                </div>

                {{-- Active Fields --}}
                <div
                        x-sortable
                        wire:end.stop="updateFieldsOrder($event.target.sortable.toArray())"
                        data-sortable-animation-duration="300"
                        class="divide-y divide-gray-200 dark:divide-white/10"
                >
                    @foreach($this->activeFields as $field)
                        <div
                                x-sortable-item="{{ $field->getKey() }}"
                                wire:key="field-{{ $field->getKey() }}"
                                class="grid grid-cols-[40px_1fr_minmax(120px,160px)_minmax(100px,140px)_minmax(80px,120px)_50px] items-center min-h-[50px] hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75"
                        >
                            {{-- Drag Handle --}}
                            <div class="flex items-center justify-center py-3">
                                <div x-sortable-handle
                                     class="cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                        <circle cx="7" cy="5" r="1.5"/>
                                        <circle cx="13" cy="5" r="1.5"/>
                                        <circle cx="7" cy="10" r="1.5"/>
                                        <circle cx="13" cy="10" r="1.5"/>
                                        <circle cx="7" cy="15" r="1.5"/>
                                        <circle cx="13" cy="15" r="1.5"/>
                                    </svg>
                                </div>
                            </div>

                            {{-- Name --}}
                            <div class="flex items-center gap-2 px-3 py-3 text-sm text-gray-950 dark:text-white min-w-0">
                                @if($field->typeData?->icon)
                                    <x-filament::icon :icon="$field->typeData->icon"
                                                      class="h-4 w-4 text-gray-400 shrink-0"/>
                                @endif
                                <span class="truncate">{{ $field->name }}</span>
                            </div>

                            {{-- Type --}}
                            <div class="px-3 py-3 text-sm text-gray-600 dark:text-gray-400 truncate">
                                {{ $field->typeData?->label }}
                            </div>

                            {{-- Constraints --}}
                            <div class="px-3 py-3 text-sm text-gray-600 dark:text-gray-400 truncate">
                                @if($field->settings?->unique_per_entity_type)
                                    {{ __('custom-fields::custom-fields.common.unique') }}
                                @elseif($field->validation_rules?->has('required'))
                                    {{ __('custom-fields::custom-fields.common.required') }}
                                @endif
                            </div>

                            {{-- Properties --}}
                            <div class="px-3 py-3 flex items-center min-h-[28px]">
                                @if($field->system_defined)
                                    <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400 whitespace-nowrap">
                                        <x-filament::icon icon="heroicon-o-cog-6-tooth" class="h-3 w-3 shrink-0"/>
                                        {{ __('custom-fields::custom-fields.common.system') }}
                                    </span>
                                @endif
                            </div>

                            {{-- Actions --}}
                            <div class="flex items-center justify-center py-3">
                                <x-filament-actions::group
                                        :actions="array_filter([
                                        ($this->editFieldAction)(['fieldId' => $field->getKey()]),
                                        $field->isActive() && !$field->isSystemDefined()
                                            ? ($this->deactivateFieldAction)(['fieldId' => $field->getKey()])
                                            : null,
                                        !$field->isActive()
                                            ? ($this->activateFieldAction)(['fieldId' => $field->getKey()])
                                            : null,
                                        !$field->isActive() && !$field->isSystemDefined()
                                            ? ($this->deleteFieldAction)(['fieldId' => $field->getKey()])
                                            : null,
                                    ])"
                                        dropdown-placement="bottom-end"
                                />
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Archived Section (Collapsible inside table) --}}
                @if($this->inactiveFields->count())
                    <div
                            x-data="{ open: {{ filled($search) ? 'true' : 'false' }} }"
                            x-init="$watch('$wire.search', value => open = value.length > 0)"
                            class="border-t border-gray-200 dark:border-white/10">
                        {{-- Archived Header Row --}}
                        <button
                                type="button"
                                x-on:click="open = !open"
                                class="w-full grid grid-cols-[40px_1fr] items-center hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75 text-left"
                        >
                            <div class="flex items-center justify-center py-3">
                                <x-filament::icon
                                        icon="heroicon-m-chevron-up"
                                        class="h-4 w-4 text-gray-400 transition-transform duration-200"
                                        x-bind:class="{ 'rotate-180': !open }"
                                />
                            </div>
                            <div class="px-3 py-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ __('custom-fields::custom-fields.common.archived') }}
                                <span class="text-gray-400 dark:text-gray-500">({{ $this->inactiveFields->count() }})</span>
                            </div>
                        </button>

                        {{-- Archived Fields --}}
                        <div x-show="open" x-collapse class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach($this->inactiveFields as $field)
                                <div
                                        wire:key="inactive-field-{{ $field->getKey() }}"
                                        class="grid grid-cols-[40px_1fr_minmax(120px,160px)_minmax(100px,140px)_minmax(80px,120px)_50px] items-center min-h-[50px] hover:bg-gray-50 dark:hover:bg-white/5 transition duration-75"
                                >
                                    {{-- Empty (no drag handle) --}}
                                    <div></div>

                                    {{-- Name --}}
                                    <div class="flex items-center gap-2 px-3 py-3 text-sm text-gray-950 dark:text-white opacity-60 min-w-0">
                                        @if($field->typeData?->icon)
                                            <x-filament::icon :icon="$field->typeData->icon"
                                                              class="h-4 w-4 text-gray-400 shrink-0"/>
                                        @endif
                                        <span class="truncate">{{ $field->name }}</span>
                                    </div>

                                    {{-- Type --}}
                                    <div class="px-3 py-3 text-sm text-gray-600 dark:text-gray-400 opacity-60 truncate">
                                        {{ $field->typeData?->label }}
                                    </div>

                                    {{-- Constraints --}}
                                    <div class="px-3 py-3 text-sm text-gray-600 dark:text-gray-400 opacity-60 truncate">
                                        @if($field->settings?->unique_per_entity_type)
                                            {{ __('custom-fields::custom-fields.common.unique') }}
                                        @elseif($field->validation_rules?->has('required'))
                                            {{ __('custom-fields::custom-fields.common.required') }}
                                        @endif
                                    </div>

                                    {{-- Properties --}}
                                    <div class="px-3 py-3 flex items-center min-h-[28px] opacity-60">
                                        @if($field->system_defined)
                                            <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400 whitespace-nowrap">
                                                <x-filament::icon icon="heroicon-o-cog-6-tooth"
                                                                  class="h-3 w-3 shrink-0"/>
                                                {{ __('custom-fields::custom-fields.common.system') }}
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Actions (no opacity) --}}
                                    <div class="flex items-center justify-center py-3">
                                        <x-filament-actions::group
                                                :actions="array_filter([
                                                ($this->editFieldAction)(['fieldId' => $field->getKey()]),
                                                ($this->activateFieldAction)(['fieldId' => $field->getKey()]),
                                                !$field->isSystemDefined()
                                                    ? ($this->deleteFieldAction)(['fieldId' => $field->getKey()])
                                                    : null,
                                            ])"
                                                dropdown-placement="bottom-end"
                                        />
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Empty State --}}
        @if($this->activeFields->count() === 0 && $this->inactiveFields->count() === 0)
            <div class="px-6 py-12">
                <div class="mx-auto grid max-w-md justify-items-center text-center">
                    <div class="mb-4 rounded-full bg-gray-100 p-3 dark:bg-gray-800">
                        @if($search)
                            <x-filament::icon
                                    icon="heroicon-m-magnifying-glass"
                                    class="h-6 w-6 text-gray-400 dark:text-gray-500"
                            />
                        @else
                            <x-filament::icon
                                    icon="heroicon-o-squares-plus"
                                    class="h-6 w-6 text-gray-400 dark:text-gray-500"
                            />
                        @endif
                    </div>
                    <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                        @if($search)
                            {{ __('custom-fields::custom-fields.empty_states.search_no_results.heading') }}
                        @else
                            {{ __('custom-fields::custom-fields.empty_states.fields_no_sections.heading') }}
                        @endif
                    </h4>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if($search)
                            {{ __('custom-fields::custom-fields.empty_states.search_no_results.description') }}
                        @else
                            {{ __('custom-fields::custom-fields.empty_states.fields_no_sections.description') }}
                        @endif
                    </p>
                </div>
            </div>
        @endif
    </div>

    <x-filament-actions::modals/>
</div>
