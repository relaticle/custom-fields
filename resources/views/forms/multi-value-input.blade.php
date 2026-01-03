@php
    $fieldWrapperView = $getFieldWrapperView();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $inputType = $getInputType();
    $allowMultiple = $getAllowMultiple();
    $maxValues = $getMaxValues();
    $addLabel = $getAddLabel();
    $emptyStateLabel = $getEmptyStateLabel();
    $placeholder = $getPlaceholder();
    $inputmode = match($inputType) { 'email' => 'email', 'url' => 'url', default => 'text' };
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    class="fi-fo-multi-value-input-wrp"
>
    <x-filament::input.wrapper
        :disabled="$isDisabled"
        :valid="! $errors->has($statePath)"
        :attributes="
            \Filament\Support\prepare_inherited_attributes($attributes)
                ->class(['fi-fo-multi-value-input'])
        "
    >
        <div
            x-data="{
                state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                isOpen: false,
                newValue: '',
                allowMultiple: @js($allowMultiple),
                maxValues: @js($maxValues),
                isDisabled: @js($isDisabled),
                maxVisibleValues: 3,
                copiedIndex: null,

                init() {
                    if (!Array.isArray(this.state)) {
                        this.state = this.state ? [this.state] : [];
                    }
                    this.state = this.state.filter(v => v && v.trim() !== '');
                },

                get canAddMore() {
                    if (!this.allowMultiple) {
                        return this.state.length === 0;
                    }
                    return this.state.length < this.maxValues;
                },

                get hasValues() {
                    return this.state.length > 0;
                },

                get singleValue() {
                    return this.state[0] || '';
                },

                get visibleValues() {
                    return this.state.slice(0, this.maxVisibleValues);
                },

                get hiddenCount() {
                    return Math.max(0, this.state.length - this.maxVisibleValues);
                },

                open() {
                    if (!this.isDisabled) {
                        this.isOpen = true;
                        this.$nextTick(() => {
                            this.$refs.newInput?.focus();
                        });
                    }
                },

                close() {
                    this.isOpen = false;
                    this.newValue = '';
                },

                toggle() {
                    this.isOpen ? this.close() : this.open();
                },

                addValue() {
                    const value = this.newValue.trim();
                    if (!value || !this.canAddMore) return;

                    if (this.state.includes(value)) {
                        new FilamentNotification()
                            .title('{{ __('custom-fields::custom-fields.validation.duplicate_value') }}')
                            .body('{{ __('custom-fields::custom-fields.validation.value_already_exists') }}')
                            .warning()
                            .send();
                        return;
                    }

                    this.state.push(value);
                    this.newValue = '';

                    if (!this.allowMultiple) {
                        this.close();
                    } else {
                        this.$nextTick(() => {
                            this.$refs.newInput?.focus();
                        });
                    }
                },

                setSingleValue(value) {
                    const trimmed = value.trim();
                    this.state = trimmed ? [trimmed] : [];
                },

                deleteValue(valueToDelete) {
                    this.state = this.state.filter((v) => v !== valueToDelete);
                    if (this.state.length === 0 && !this.allowMultiple) {
                        this.close();
                    }
                },

                handleEnter(e) {
                    e.preventDefault();
                    this.addValue();
                },

                reorderValues(event) {
                    const reordered = this.state.splice(event.oldIndex, 1)[0];
                    this.state.splice(event.newIndex, 0, reordered);
                    this.state = [...this.state];
                },

                copyToClipboard(text, index) {
                    window.navigator.clipboard.writeText(text);
                    this.copiedIndex = index;
                    setTimeout(() => {
                        this.copiedIndex = null;
                    }, 2000);
                }
            }"
            x-on:click.outside="close()"
            x-on:keydown.escape.window="close()"
            class="relative w-full"
        >
            {{-- Single Value Mode: simple inline input --}}
            <template x-if="!allowMultiple && state.length <= 1">
                <input
                    type="text"
                    inputmode="{{ $inputmode }}"
                    :value="singleValue"
                    x-on:input="setSingleValue($event.target.value)"
                    :disabled="isDisabled"
                    class="fi-input block w-full border-none bg-transparent py-1.5 px-3 text-sm text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 dark:text-white dark:placeholder:text-gray-500 dark:disabled:text-gray-400"
                    placeholder="{{ $placeholder }}"
                />
            </template>

            {{-- Multiple Values Mode OR Single mode with legacy data: Values with popover --}}
            <template x-if="allowMultiple || state.length > 1">
                <div>
                    {{-- Trigger Area --}}
                    <button
                        type="button"
                        x-on:click="toggle()"
                        :disabled="isDisabled"
                        class="flex w-full min-h-[2.25rem] items-center gap-1.5 py-1.5 px-3 text-left focus:outline-none"
                    >
                        {{-- Content area (empty state or values) --}}
                        <div class="flex flex-1 items-center gap-2 overflow-hidden">
                            {{-- Empty State --}}
                            <template x-if="!hasValues">
                                <span class="text-sm text-gray-400 dark:text-gray-500">
                                    {{ $emptyStateLabel }}
                                </span>
                            </template>

                            {{-- Visible Values (underlined, copiable) --}}
                            <template x-for="(value, index) in visibleValues" :key="`${value}-${index}`">
                                <span
                                    x-on:click.stop="copyToClipboard(value, index)"
                                    class="text-sm text-primary-600 dark:text-primary-400 underline decoration-gray-300 dark:decoration-gray-600 decoration-1 underline-offset-2 truncate max-w-[140px] rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                                    x-text="value"
                                ></span>
                            </template>

                            {{-- "+N more" indicator --}}
                            <template x-if="hiddenCount > 0">
                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                    +<span x-text="hiddenCount"></span> more
                                </span>
                            </template>
                        </div>

                        {{-- Chevron indicator --}}
                        <x-heroicon-m-chevron-down
                            class="size-4 text-gray-400 dark:text-gray-500 shrink-0 transition-transform duration-200"
                            x-bind:class="{ 'rotate-180': isOpen }"
                            aria-hidden="true"
                        />
                    </button>

                    {{-- Popover Panel --}}
                    <div
                        x-cloak
                        x-show="isOpen"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        x-ref="panel"
                        class="absolute left-0 right-0 top-full z-10 mt-1 rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    >
                        {{-- Existing Values List --}}
                        <div wire:ignore>
                            <template x-if="hasValues">
                                <div
                                    x-sortable
                                    x-on:end.stop="reorderValues($event)"
                                    class="max-h-[280px] overflow-y-auto rounded-t-lg [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-gray-300 [&::-webkit-scrollbar-thumb]:rounded-full dark:[&::-webkit-scrollbar-thumb]:bg-gray-600"
                                >
                                    <template x-for="(value, index) in state" :key="`${value}-${index}`">
                                        <div
                                            :x-sortable-item="index"
                                            class="group flex items-center gap-2 px-3 py-2 border-b border-gray-100 dark:border-gray-800 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 first:rounded-t-lg last:rounded-b-lg transition-colors"
                                        >
                                            {{-- Drag Handle --}}
                                            <div x-sortable-handle class="shrink-0 cursor-grab active:cursor-grabbing" x-show="state.length > 1">
                                                <svg class="size-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <circle cx="7" cy="5" r="1.5"/>
                                                    <circle cx="13" cy="5" r="1.5"/>
                                                    <circle cx="7" cy="10" r="1.5"/>
                                                    <circle cx="13" cy="10" r="1.5"/>
                                                    <circle cx="7" cy="15" r="1.5"/>
                                                    <circle cx="13" cy="15" r="1.5"/>
                                                </svg>
                                            </div>

                                            {{-- Value (Click to Copy) --}}
                                            <div
                                                x-on:click="copyToClipboard(value, index)"
                                                class="inline-flex items-center gap-1.5 py-0.5 rounded cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                            >
                                                <span
                                                    class="text-sm text-primary-600 dark:text-primary-400 underline decoration-gray-300 dark:decoration-gray-600 decoration-1 underline-offset-2"
                                                    x-text="value"
                                                ></span>
                                                <x-heroicon-m-clipboard-document
                                                    x-show="copiedIndex !== index"
                                                    class="size-4 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity shrink-0"
                                                    aria-hidden="true"
                                                />
                                                <x-heroicon-m-check
                                                    x-show="copiedIndex === index"
                                                    x-cloak
                                                    class="size-4 text-green-500 shrink-0"
                                                    aria-hidden="true"
                                                />
                                            </div>

                                            <div class="flex-1"></div>

                                            {{-- Delete Button --}}
                                            <button
                                                type="button"
                                                x-on:click="deleteValue(value)"
                                                class="opacity-0 group-hover:opacity-100 focus:opacity-100 shrink-0 rounded p-1 text-gray-400 hover:text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                            >
                                                <x-heroicon-m-trash class="size-4" aria-hidden="true" />
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        {{-- Add New Value --}}
                        <template x-if="canAddMore">
                            <div class="flex items-center gap-2 px-3 py-2 border-t border-gray-100 dark:border-gray-800">
                                {{-- Plus Icon (Left) --}}
                                <x-heroicon-m-plus class="size-4 text-gray-400 shrink-0" aria-hidden="true" />

                                <input
                                    type="text"
                                    inputmode="{{ $inputmode }}"
                                    x-model="newValue"
                                    x-ref="newInput"
                                    x-on:keydown.enter="handleEnter($event)"
                                    class="flex-1 bg-transparent border-0 p-0 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-0 focus:outline-none"
                                    placeholder="{{ $addLabel }}..."
                                />
                                <button
                                    type="button"
                                    x-on:click="addValue()"
                                    :disabled="!newValue.trim()"
                                    class="shrink-0 rounded p-1 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <x-heroicon-m-arrow-right class="size-4" aria-hidden="true" />
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
