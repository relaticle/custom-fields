@php
    $fieldWrapperView = $getFieldWrapperView();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $allowMultiple = $getAllowMultiple();
    $maxValues = $getMaxValues();
    $addLabel = $getAddLabel();
    $emptyStateLabel = $getEmptyStateLabel();
    $placeholder = $getPlaceholder() ?? '(555) 123-4567';
    $defaultCountry = $getDefaultCountry();
    $countryOptions = $getCountryOptions();
    $countryOptionsWithNames = $getCountryOptionsWithNames();
    $componentId = 'phone-input-' . str_replace('.', '-', $statePath);
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    class="fi-fo-phone-input-wrp"
>
    <x-filament::input.wrapper
        :disabled="$isDisabled"
        :valid="! $errors->has($statePath)"
        :attributes="
            \Filament\Support\prepare_inherited_attributes($attributes)
                ->class(['fi-fo-phone-input'])
        "
    >
        <div
            x-data="{
                state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                isOpen: false,
                allowMultiple: @js($allowMultiple),
                maxValues: @js($maxValues),
                isDisabled: @js($isDisabled),
                defaultCountry: @js($defaultCountry),
                countryOptions: @js($countryOptions),
                countryOptionsWithNames: @js($countryOptionsWithNames),
                maxVisiblePills: 2,
                countrySearch: '',
                activeCountryDropdown: null,
                highlightedIndex: -1,
                componentId: @js($componentId),

                init() {
                    if (!Array.isArray(this.state)) {
                        this.state = this.state ? [this.state] : [];
                    }
                    if (this.state.length === 0) {
                        this.state = [{ country: this.defaultCountry, number: '' }];
                    }
                    this.state = this.state.map(entry => {
                        return {
                            country: entry?.country || this.defaultCountry,
                            number: entry?.number || ''
                        };
                    });
                },

                get canAddMore() {
                    return this.allowMultiple && this.state.length < this.maxValues;
                },

                get hasValues() {
                    return this.state.some(entry => entry.number && entry.number.trim() !== '');
                },

                get visibleEntries() {
                    return this.state.filter(entry => entry.number && entry.number.trim() !== '').slice(0, this.maxVisiblePills);
                },

                get hiddenCount() {
                    const filledCount = this.state.filter(entry => entry.number && entry.number.trim() !== '').length;
                    return Math.max(0, filledCount - this.maxVisiblePills);
                },

                get filteredCountries() {
                    if (!this.countrySearch) {
                        return Object.entries(this.countryOptionsWithNames);
                    }
                    const search = this.countrySearch.toLowerCase();
                    return Object.entries(this.countryOptionsWithNames).filter(([code, label]) =>
                        code.toLowerCase().includes(search) || label.toLowerCase().includes(search)
                    );
                },

                get highlightedOptionId() {
                    if (this.highlightedIndex < 0 || this.highlightedIndex >= this.filteredCountries.length) {
                        return null;
                    }
                    const [code] = this.filteredCountries[this.highlightedIndex];
                    return this.getOptionId(this.activeCountryDropdown, code);
                },

                getOptionId(index, code) {
                    return `${this.componentId}-country-option-${index}-${code}`;
                },

                getListboxId(index) {
                    return `${this.componentId}-country-listbox-${index}`;
                },

                formatDisplay(entry) {
                    if (!entry.number) return '';
                    const countryCode = this.getCallingCode(entry.country);
                    return '+' + countryCode + ' ' + entry.number;
                },

                getCallingCode(country) {
                    const match = this.countryOptions[country];
                    if (match) {
                        const code = match.match(/(\d+)/);
                        return code ? code[1] : '';
                    }
                    return '';
                },

                getCountryLabel(country) {
                    return this.countryOptions[country] || country;
                },

                getCountryAriaLabel(country) {
                    const label = this.countryOptionsWithNames[country] || country;
                    return `{{ __('custom-fields::custom-fields.phone.change_country') }}, ${label}`;
                },

                open() {
                    if (!this.isDisabled) {
                        this.isOpen = true;
                    }
                },

                close() {
                    this.isOpen = false;
                    this.activeCountryDropdown = null;
                    this.countrySearch = '';
                    this.highlightedIndex = -1;
                },

                toggle() {
                    this.isOpen ? this.close() : this.open();
                },

                openCountryDropdown(index) {
                    this.activeCountryDropdown = index;
                    this.countrySearch = '';
                    this.highlightedIndex = -1;
                    this.announceResults();
                },

                closeCountryDropdown() {
                    this.activeCountryDropdown = null;
                    this.countrySearch = '';
                    this.highlightedIndex = -1;
                },

                selectCountry(index, code) {
                    this.updateEntry(index, 'country', code);
                    this.closeCountryDropdown();
                },

                selectHighlightedCountry(index) {
                    if (this.highlightedIndex >= 0 && this.highlightedIndex < this.filteredCountries.length) {
                        const [code] = this.filteredCountries[this.highlightedIndex];
                        this.selectCountry(index, code);
                    }
                },

                highlightNext() {
                    if (this.filteredCountries.length === 0) return;
                    this.highlightedIndex = (this.highlightedIndex + 1) % this.filteredCountries.length;
                    this.scrollHighlightedIntoView();
                },

                highlightPrev() {
                    if (this.filteredCountries.length === 0) return;
                    this.highlightedIndex = this.highlightedIndex <= 0
                        ? this.filteredCountries.length - 1
                        : this.highlightedIndex - 1;
                    this.scrollHighlightedIntoView();
                },

                highlightFirst() {
                    if (this.filteredCountries.length === 0) return;
                    this.highlightedIndex = 0;
                    this.scrollHighlightedIntoView();
                },

                highlightLast() {
                    if (this.filteredCountries.length === 0) return;
                    this.highlightedIndex = this.filteredCountries.length - 1;
                    this.scrollHighlightedIntoView();
                },

                scrollHighlightedIntoView() {
                    this.$nextTick(() => {
                        const optionId = this.highlightedOptionId;
                        if (optionId) {
                            const option = document.getElementById(optionId);
                            if (option) {
                                option.scrollIntoView({ block: 'nearest' });
                            }
                        }
                    });
                },

                announceResults() {
                    this.$nextTick(() => {
                        const count = this.filteredCountries.length;
                        const message = count === 0
                            ? '{{ __('custom-fields::custom-fields.phone.no_results') }}'
                            : (count === 1
                                ? '{{ __('custom-fields::custom-fields.phone.one_result') }}'
                                : `${count} {{ __('custom-fields::custom-fields.phone.results_available') }}`);
                        const announcer = this.$refs.srAnnouncer;
                        if (announcer) {
                            announcer.textContent = message;
                        }
                    });
                },

                handleSearchKeydown(event, index) {
                    switch (event.key) {
                        case 'ArrowDown':
                            event.preventDefault();
                            this.highlightNext();
                            break;
                        case 'ArrowUp':
                            event.preventDefault();
                            this.highlightPrev();
                            break;
                        case 'Home':
                            event.preventDefault();
                            this.highlightFirst();
                            break;
                        case 'End':
                            event.preventDefault();
                            this.highlightLast();
                            break;
                        case 'Enter':
                            event.preventDefault();
                            this.selectHighlightedCountry(index);
                            break;
                        case 'Escape':
                            event.preventDefault();
                            this.closeCountryDropdown();
                            break;
                        case 'Tab':
                            this.closeCountryDropdown();
                            break;
                    }
                },

                handleButtonKeydown(event, index) {
                    switch (event.key) {
                        case 'ArrowDown':
                        case 'ArrowUp':
                        case ' ':
                            event.preventDefault();
                            if (this.activeCountryDropdown !== index) {
                                this.openCountryDropdown(index);
                            }
                            break;
                        case 'Escape':
                            if (this.activeCountryDropdown === index) {
                                event.preventDefault();
                                this.closeCountryDropdown();
                            }
                            break;
                    }
                },

                addEntry() {
                    if (this.canAddMore) {
                        this.state = [...this.state, { country: this.defaultCountry, number: '' }];
                    }
                },

                updateEntry(index, field, value) {
                    if (this.state[index]) {
                        const updated = [...this.state];
                        updated[index] = { ...updated[index], [field]: value };
                        this.state = updated;
                    }
                },

                removeEntry(index) {
                    if (this.state.length > 1) {
                        this.state = this.state.filter((_, i) => i !== index);
                    } else {
                        this.state = [{ country: this.defaultCountry, number: '' }];
                    }
                }
            }"
            x-on:click.outside="close()"
            x-on:keydown.escape.window="close()"
            x-effect="if (countrySearch !== '') { highlightedIndex = 0; announceResults(); }"
            class="relative w-full"
        >
            {{-- Screen reader announcer for live updates --}}
            <div
                x-ref="srAnnouncer"
                class="sr-only"
                role="status"
                aria-live="polite"
                aria-atomic="true"
            ></div>

            {{-- Single Value Mode --}}
            <template x-if="!allowMultiple">
                <div class="flex w-full items-center">
                    {{-- Country Selector Button --}}
                    <div class="relative shrink-0">
                        <button
                            type="button"
                            role="combobox"
                            :aria-expanded="activeCountryDropdown === 0"
                            :aria-controls="getListboxId(0)"
                            :aria-activedescendant="activeCountryDropdown === 0 ? highlightedOptionId : null"
                            :aria-label="getCountryAriaLabel(state[0]?.country)"
                            aria-haspopup="listbox"
                            aria-autocomplete="list"
                            x-on:click.stop="activeCountryDropdown === 0 ? closeCountryDropdown() : openCountryDropdown(0)"
                            x-on:keydown="handleButtonKeydown($event, 0)"
                            :disabled="isDisabled"
                            class="flex items-center gap-1 py-1.5 pl-3 pr-1.5 text-sm text-gray-950 dark:text-white hover:bg-gray-50 dark:hover:bg-white/5 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed rounded-l-lg"
                        >
                            <span x-text="getCountryLabel(state[0]?.country)" class="font-medium text-xs"></span>
                            <x-heroicon-m-chevron-down class="size-3.5 text-gray-400" x-bind:class="{ 'rotate-180': activeCountryDropdown === 0 }" aria-hidden="true" />
                        </button>

                        {{-- Country Dropdown --}}
                        <div
                            x-cloak
                            x-show="activeCountryDropdown === 0"
                            x-float.placement.bottom-start.flip.teleport.offset="{ offset: 4 }"
                            x-on:click.outside="closeCountryDropdown()"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="opacity-0 scale-95"
                            x-transition:enter-end="opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="opacity-100 scale-100"
                            x-transition:leave-end="opacity-0 scale-95"
                            class="z-50 w-64 rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                        >
                            <div class="border-b border-gray-100 dark:border-gray-800">
                                <div class="relative">
                                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" aria-hidden="true" />
                                    <input
                                        type="text"
                                        role="searchbox"
                                        x-model="countrySearch"
                                        x-on:click.stop
                                        x-on:keydown="handleSearchKeydown($event, 0)"
                                        x-ref="singleCountrySearch"
                                        x-init="$watch('activeCountryDropdown', value => { if (value === 0) $nextTick(() => $refs.singleCountrySearch?.focus()) })"
                                        :aria-controls="getListboxId(0)"
                                        :aria-activedescendant="highlightedOptionId"
                                        aria-label="{{ __('custom-fields::custom-fields.phone.search_country') }}"
                                        aria-autocomplete="list"
                                        placeholder="{{ __('custom-fields::custom-fields.phone.search_country') }}"
                                        class="w-full border-0 bg-transparent py-2.5 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0 focus:outline-none dark:text-white dark:placeholder:text-gray-500"
                                    />
                                </div>
                            </div>
                            <ul
                                :id="getListboxId(0)"
                                role="listbox"
                                aria-label="{{ __('custom-fields::custom-fields.phone.country_list') }}"
                                tabindex="-1"
                                class="max-h-60 overflow-y-auto p-1"
                            >
                                <template x-for="([code, label], optIndex) in filteredCountries" :key="'single-' + code">
                                    <li
                                        :id="getOptionId(0, code)"
                                        role="option"
                                        :aria-selected="state[0]?.country === code"
                                        tabindex="-1"
                                    >
                                        <button
                                            type="button"
                                            x-on:click.stop="selectCountry(0, code)"
                                            x-on:mouseenter="highlightedIndex = optIndex"
                                            x-on:focus="highlightedIndex = optIndex"
                                            class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors focus:outline-none"
                                            :class="{
                                                'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400': state[0]?.country === code,
                                                'bg-gray-100 dark:bg-gray-800': highlightedIndex === optIndex && state[0]?.country !== code,
                                                'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5': state[0]?.country !== code && highlightedIndex !== optIndex
                                            }"
                                        >
                                            <span x-text="label" class="truncate"></span>
                                            <x-heroicon-m-check x-show="state[0]?.country === code" class="ml-auto size-4 shrink-0 text-primary-600 dark:text-primary-400" x-cloak aria-hidden="true" />
                                        </button>
                                    </li>
                                </template>
                                <template x-if="filteredCountries.length === 0">
                                    <li class="px-2 py-3 text-center text-sm text-gray-500 dark:text-gray-400" role="status">
                                        {{ __('custom-fields::custom-fields.phone.no_results') }}
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    <div class="h-5 w-px bg-gray-200 dark:bg-gray-700 shrink-0" aria-hidden="true"></div>

                    <input
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel"
                        x-model="state[0].number"
                        x-on:input="updateEntry(0, 'number', $event.target.value)"
                        :disabled="isDisabled"
                        :aria-label="'{{ __('custom-fields::custom-fields.phone.phone_number') }}'"
                        class="fi-input min-w-0 flex-1 border-none bg-transparent py-1.5 px-3 text-sm text-gray-950 outline-none transition duration-75 placeholder:text-gray-400 focus:ring-0 disabled:text-gray-500 dark:text-white dark:placeholder:text-gray-500 dark:disabled:text-gray-400"
                        placeholder="{{ $placeholder }}"
                    />
                </div>
            </template>

            {{-- Multiple Values Mode --}}
            <template x-if="allowMultiple">
                <div>
                    <button
                        type="button"
                        x-on:click="toggle()"
                        :disabled="isDisabled"
                        :aria-expanded="isOpen"
                        aria-haspopup="dialog"
                        aria-label="{{ __('custom-fields::custom-fields.phone.manage_phone_numbers') }}"
                        class="flex w-full min-h-[2.25rem] items-center gap-1.5 py-1.5 px-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 rounded-lg"
                    >
                        <div class="flex flex-1 items-center gap-1.5 overflow-hidden">
                            <template x-if="!hasValues">
                                <span class="text-sm text-gray-400 dark:text-gray-500">{{ $emptyStateLabel }}</span>
                            </template>
                            <template x-for="(entry, index) in visibleEntries" :key="'pill-' + index">
                                <span class="inline-flex items-center gap-x-1 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 ring-1 ring-inset ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-400 dark:ring-primary-400/20 truncate max-w-[140px]">
                                    <span x-text="formatDisplay(entry)" class="truncate"></span>
                                </span>
                            </template>
                            <template x-if="hiddenCount > 0">
                                <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20">
                                    +<span x-text="hiddenCount"></span>
                                </span>
                            </template>
                        </div>
                        <x-heroicon-m-chevron-down class="size-4 text-gray-400 dark:text-gray-500 shrink-0 transition-transform duration-200" x-bind:class="{ 'rotate-180': isOpen }" aria-hidden="true" />
                    </button>

                    {{-- Popover Panel --}}
                    <div
                        x-cloak
                        x-show="isOpen"
                        x-float.placement.bottom-start.flip.offset="{ offset: 4 }"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        role="dialog"
                        aria-label="{{ __('custom-fields::custom-fields.phone.edit_phone_numbers') }}"
                        class="absolute left-0 right-0 top-full z-10 mt-1 w-full rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                    >
                        <div class="max-h-[280px] overflow-y-auto" role="list" aria-label="{{ __('custom-fields::custom-fields.phone.phone_numbers_list') }}">
                            <template x-for="(entry, index) in state" :key="'edit-' + index">
                                <div class="group flex items-center gap-2 px-3 py-2 border-b border-gray-100 dark:border-gray-800 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors" role="listitem">
                                    {{-- Country Selector --}}
                                    <div class="relative shrink-0">
                                        <button
                                            type="button"
                                            role="combobox"
                                            :aria-expanded="activeCountryDropdown === index"
                                            :aria-controls="getListboxId(index)"
                                            :aria-activedescendant="activeCountryDropdown === index ? highlightedOptionId : null"
                                            :aria-label="getCountryAriaLabel(entry.country)"
                                            aria-haspopup="listbox"
                                            aria-autocomplete="list"
                                            x-on:click.stop="activeCountryDropdown === index ? closeCountryDropdown() : openCountryDropdown(index)"
                                            x-on:keydown="handleButtonKeydown($event, index)"
                                            class="flex items-center gap-1 rounded bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1"
                                        >
                                            <span x-text="getCountryLabel(entry.country)" class="max-w-[70px] truncate"></span>
                                            <x-heroicon-m-chevron-down class="size-3 text-gray-400" x-bind:class="{ 'rotate-180': activeCountryDropdown === index }" aria-hidden="true" />
                                        </button>

                                        {{-- Country Dropdown with teleport --}}
                                        <div
                                            x-cloak
                                            x-show="activeCountryDropdown === index"
                                            x-float.placement.bottom-start.flip.teleport.offset="{ offset: 4 }"
                                            x-on:click.outside="closeCountryDropdown()"
                                            x-transition:enter="transition ease-out duration-100"
                                            x-transition:enter-start="opacity-0 scale-95"
                                            x-transition:enter-end="opacity-100 scale-100"
                                            x-transition:leave="transition ease-in duration-75"
                                            x-transition:leave-start="opacity-100 scale-100"
                                            x-transition:leave-end="opacity-0 scale-95"
                                            class="z-50 w-64 rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
                                        >
                                            <div class="border-b border-gray-100 dark:border-gray-800">
                                                <div class="relative">
                                                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-gray-400" aria-hidden="true" />
                                                    <input
                                                        type="text"
                                                        role="searchbox"
                                                        x-model="countrySearch"
                                                        x-on:click.stop
                                                        x-on:keydown="handleSearchKeydown($event, index)"
                                                        x-ref="multiCountrySearch"
                                                        :aria-controls="getListboxId(index)"
                                                        :aria-activedescendant="highlightedOptionId"
                                                        aria-label="{{ __('custom-fields::custom-fields.phone.search_country') }}"
                                                        aria-autocomplete="list"
                                                        placeholder="{{ __('custom-fields::custom-fields.phone.search_country') }}"
                                                        class="w-full border-0 bg-transparent py-2.5 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:ring-0 focus:outline-none dark:text-white dark:placeholder:text-gray-500"
                                                        x-init="$watch('activeCountryDropdown', value => { if (value === index) $nextTick(() => $el?.focus()) })"
                                                    />
                                                </div>
                                            </div>
                                            <ul
                                                :id="getListboxId(index)"
                                                role="listbox"
                                                aria-label="{{ __('custom-fields::custom-fields.phone.country_list') }}"
                                                tabindex="-1"
                                                class="max-h-48 overflow-y-auto p-1"
                                            >
                                                <template x-for="([code, label], optIndex) in filteredCountries" :key="'multi-' + index + '-' + code">
                                                    <li
                                                        :id="getOptionId(index, code)"
                                                        role="option"
                                                        :aria-selected="entry.country === code"
                                                        tabindex="-1"
                                                    >
                                                        <button
                                                            type="button"
                                                            x-on:click.stop="selectCountry(index, code)"
                                                            x-on:mouseenter="highlightedIndex = optIndex"
                                                            x-on:focus="highlightedIndex = optIndex"
                                                            class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-sm transition-colors focus:outline-none"
                                                            :class="{
                                                                'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400': entry.country === code,
                                                                'bg-gray-100 dark:bg-gray-800': highlightedIndex === optIndex && entry.country !== code,
                                                                'text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5': entry.country !== code && highlightedIndex !== optIndex
                                                            }"
                                                        >
                                                            <span x-text="label" class="truncate"></span>
                                                            <x-heroicon-m-check x-show="entry.country === code" class="ml-auto size-4 shrink-0 text-primary-600 dark:text-primary-400" x-cloak aria-hidden="true" />
                                                        </button>
                                                    </li>
                                                </template>
                                                <template x-if="filteredCountries.length === 0">
                                                    <li class="px-2 py-3 text-center text-sm text-gray-500 dark:text-gray-400" role="status">
                                                        {{ __('custom-fields::custom-fields.phone.no_results') }}
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>
                                    </div>

                                    <input
                                        type="tel"
                                        inputmode="tel"
                                        autocomplete="tel"
                                        x-model="entry.number"
                                        x-on:input="updateEntry(index, 'number', $event.target.value)"
                                        :aria-label="`{{ __('custom-fields::custom-fields.phone.phone_number') }} ${index + 1}`"
                                        class="min-w-0 flex-1 bg-transparent border-0 p-0 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-0 focus:outline-none"
                                        placeholder="{{ $placeholder }}"
                                    />

                                    <button
                                        type="button"
                                        x-on:click="removeEntry(index)"
                                        x-show="state.length > 1 || entry.number"
                                        :aria-label="`{{ __('custom-fields::custom-fields.phone.remove_phone_number') }} ${index + 1}`"
                                        class="opacity-0 group-hover:opacity-100 focus:opacity-100 shrink-0 rounded p-1 text-gray-400 hover:text-danger-500 hover:bg-danger-50 dark:hover:bg-danger-500/10 transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                    >
                                        <x-heroicon-m-x-mark class="size-4" aria-hidden="true" />
                                    </button>
                                </div>
                            </template>
                        </div>

                        <template x-if="canAddMore">
                            <div class="border-t border-gray-100 dark:border-gray-800 px-3 py-2">
                                <button
                                    type="button"
                                    x-on:click="addEntry()"
                                    aria-label="{{ $addLabel }}"
                                    class="flex w-full items-center gap-2 text-sm text-gray-500 hover:text-primary-600 dark:text-gray-400 dark:hover:text-primary-400 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1 rounded"
                                >
                                    <x-heroicon-m-plus class="size-4" aria-hidden="true" />
                                    {{ $addLabel }}
                                </button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
