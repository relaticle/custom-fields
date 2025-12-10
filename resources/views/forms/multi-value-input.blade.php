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
                maxVisiblePills: 3,

                init() {
                    if (!Array.isArray(this.state)) {
                        this.state = this.state ? [this.state] : [];
                    }
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

                get visiblePills() {
                    return this.state.slice(0, this.maxVisiblePills);
                },

                get hiddenCount() {
                    return Math.max(0, this.state.length - this.maxVisiblePills);
                },

                open() {
                    if (!this.isDisabled) {
                        this.isOpen = true;
                        this.$nextTick(() => {
                            const firstInput = this.$refs.panel?.querySelector('input');
                            if (firstInput) firstInput.focus();
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

                    if (!this.state.includes(value)) {
                        this.state = [...this.state, value];
                    }

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

                updateValue(index, newVal) {
                    const value = newVal.trim();
                    if (!value) {
                        this.removeValue(index);
                        return;
                    }
                    if (this.state[index] !== value) {
                        const updated = [...this.state];
                        updated[index] = value;
                        this.state = updated;
                    }
                },

                removeValue(index) {
                    this.state = this.state.filter((_, i) => i !== index);
                    if (this.state.length === 0 && !this.allowMultiple) {
                        this.close();
                    }
                },

                handleEnter(e) {
                    e.preventDefault();
                    this.addValue();
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

            {{-- Multiple Values Mode OR Single mode with legacy data: Pills with popover --}}
            <template x-if="allowMultiple || state.length > 1">
                <div>
                    {{-- Trigger Area --}}
                    <button
                        type="button"
                        x-on:click="toggle()"
                        :disabled="isDisabled"
                        class="flex w-full min-h-[2.25rem] items-center gap-1.5 py-1.5 px-3 text-left focus:outline-none"
                    >
                        {{-- Content area (empty state or pills) --}}
                        <div class="flex flex-1 items-center gap-1.5 overflow-hidden">
                            {{-- Empty State --}}
                            <template x-if="!hasValues">
                                <span class="text-sm text-gray-400 dark:text-gray-500">
                                    {{ $emptyStateLabel }}
                                </span>
                            </template>

                            {{-- Visible Pills --}}
                            <template x-for="(value, index) in visiblePills" :key="'pill-' + index">
                                <span
                                    class="inline-flex items-center gap-x-1 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20 truncate max-w-[140px]"
                                >
                                    <span x-text="value" class="truncate"></span>
                                </span>
                            </template>

                            {{-- "+N more" badge --}}
                            <template x-if="hiddenCount > 0">
                                <span
                                    class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20"
                                >
                                    +<span x-text="hiddenCount"></span>
                                </span>
                            </template>
                        </div>

                        {{-- Chevron indicator --}}
                        <svg
                            class="size-4 text-gray-400 dark:text-gray-500 shrink-0 transition-transform duration-200"
                            :class="{ 'rotate-180': isOpen }"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
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
                        <template x-if="hasValues">
                            <div class="max-h-[240px] overflow-y-auto m-1 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-gray-300 [&::-webkit-scrollbar-thumb]:rounded-full dark:[&::-webkit-scrollbar-thumb]:bg-gray-600">
                                <template x-for="(value, index) in state" :key="'edit-' + index">
                                    <div
                                        class="group flex items-center gap-2 px-3 py-2 border-b border-gray-100 dark:border-gray-800 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
                                    >
                                        <input
                                            type="text"
                                            inputmode="{{ $inputmode }}"
                                            :value="value"
                                            x-on:blur="updateValue(index, $event.target.value)"
                                            x-on:keydown.enter.prevent="$event.target.blur()"
                                            class="flex-1 bg-transparent border-0 p-0 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-0 focus:outline-none"
                                            placeholder="{{ $placeholder }}"
                                        />
                                        <button
                                            type="button"
                                            x-on:click="removeValue(index)"
                                            class="opacity-0 group-hover:opacity-100 focus:opacity-100 shrink-0 rounded p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-all"
                                        >
                                            <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Add New Value --}}
                        <template x-if="canAddMore">
                            <div class="flex items-center gap-2 px-3 py-2 border-t border-gray-100 dark:border-gray-800">
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
                                    class="shrink-0 rounded p-1 text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-500/10 transition-all"
                                >
                                    <svg class="size-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
