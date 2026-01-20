@php
    $fieldWrapperView = $getFieldWrapperView();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $allowMultiple = $getAllowMultiple();
    $maxValues = $getMaxValues();
    $maxVisiblePills = $getMaxVisiblePills();
    $addLabel = $getAddLabel();
    $emptyStateLabel = $getEmptyStateLabel();
    $placeholder = $getPlaceholder() ?? __('Search records...');
    $key = $getKey();

    // Get initial records data for selected values
    $state = $getState() ?? [];
    $selectedIds = is_array($state) ? array_filter($state) : [];
    $initialRecords = $getRecordsByIds($selectedIds);
    $initialOptions = $getInitialOptions();
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    class="fi-fo-record-select-input-wrp"
>
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            search: '',
            isSearching: false,
            searchResults: [],
            componentKey: @js($key),
            allowMultiple: @js($allowMultiple),
            maxValues: @js($maxValues),
            isDisabled: @js($isDisabled),
            recordsCache: @js($initialRecords),
            initialOptions: @js(array_values($initialOptions)),
            maxVisibleValues: @js($maxVisiblePills),

            init() {
                if (!Array.isArray(this.state)) {
                    this.state = this.state ? [this.state] : [];
                }
                this.state = this.state.filter(v => v && v !== '');

                this.$watch('search', (value) => {
                    if (value.trim().length >= 2) {
                        this.performSearch();
                    } else {
                        this.searchResults = [];
                    }
                });
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

            get selectedRecords() {
                return this.state.map(id => this.recordsCache[id] || { id, label: id, avatar: null }).filter(Boolean);
            },

            get visibleRecords() {
                return this.selectedRecords.slice(0, this.maxVisibleValues);
            },

            get hiddenCount() {
                return Math.max(0, this.selectedRecords.length - this.maxVisibleValues);
            },

            get sortedOptions() {
                const searchLower = this.search.toLowerCase().trim();

                // If searching (>=2 chars) and have server results, use those
                if (searchLower.length >= 2 && this.searchResults.length > 0) {
                    return this.addLastSelectedFlag(this.sortBySelected([...this.searchResults]));
                }

                // Otherwise filter initial options client-side
                let options = [...this.initialOptions];
                if (searchLower) {
                    options = options.filter(opt => opt.label.toLowerCase().includes(searchLower));
                }

                return this.addLastSelectedFlag(this.sortBySelected(options));
            },

            sortBySelected(options) {
                return options.sort((a, b) => {
                    const aSelected = this.state.includes(a.id);
                    const bSelected = this.state.includes(b.id);
                    if (aSelected && !bSelected) return -1;
                    if (!aSelected && bSelected) return 1;
                    return 0;
                });
            },

            addLastSelectedFlag(options) {
                const selectedCount = options.filter(opt => this.state.includes(opt.id)).length;
                return options.map((opt, index) => ({
                    ...opt,
                    isLastSelected: index === selectedCount - 1 && selectedCount > 0
                }));
            },

            isSelected(recordId) {
                return this.state.includes(recordId);
            },

            get emptyStateMessage() {
                const searchLength = this.search.trim().length;
                if (searchLength >= 2) {
                    return '{{ __('No records found') }}';
                }
                if (searchLength > 0) {
                    return '{{ __('Type at least 2 characters to search') }}';
                }
                if (this.initialOptions.length === 0) {
                    return '{{ __('No records available') }}';
                }
                return '';
            },

            async performSearch() {
                const query = this.search.trim();

                if (query.length < 2) {
                    this.searchResults = [];
                    return;
                }

                this.isSearching = true;

                try {
                    const results = await $wire.callSchemaComponentMethod(
                        this.componentKey,
                        'getSearchResultsForJs',
                        { search: query }
                    );
                    this.searchResults = Array.isArray(results) ? results : Object.values(results || {});
                } catch {
                    this.searchResults = [];
                } finally {
                    this.isSearching = false;
                }
            },

            isOpen() {
                return this.$refs.panel?._x_isShown === true;
            },

            togglePanel() {
                if (this.isDisabled) return;
                this.$refs.panel?.toggle(this.$refs.trigger);
                if (this.isOpen()) {
                    this.search = '';
                    this.searchResults = [];
                    this.$nextTick(() => this.$refs.searchInput?.focus());
                }
            },

            openPanel() {
                if (this.isDisabled) return;
                this.$refs.panel?.open(this.$refs.trigger);
                this.search = '';
                this.searchResults = [];
                this.$nextTick(() => this.$refs.searchInput?.focus());
            },

            closePanel() {
                this.$refs.panel?.close();
                this.search = '';
                this.searchResults = [];
            },

            toggleRecord(record) {
                if (this.isSelected(record.id)) {
                    this.removeRecord(record.id);
                } else {
                    this.selectRecord(record);
                }
            },

            selectRecord(record) {
                // In multi-select mode, check if we can add more
                if (this.allowMultiple && !this.canAddMore) return;

                if (this.state.includes(record.id)) return;

                // Cache the record data (without isLastSelected flag)
                this.recordsCache[record.id] = {
                    id: record.id,
                    label: record.label,
                    avatar: record.avatar,
                    avatarShape: record.avatarShape
                };

                if (this.allowMultiple) {
                    // Use spread for proper reactivity
                    this.state = [...this.state, record.id];
                } else {
                    // Single-select: replace the current value
                    this.state = [record.id];
                    this.closePanel();
                }
            },

            removeRecord(recordId) {
                this.state = this.state.filter(id => id !== recordId);
            }
        }"
        x-on:click.outside="closePanel()"
        x-on:keydown.esc="isOpen() && (closePanel(), $event.stopPropagation())"
        class="relative w-full"
    >
        <x-filament::input.wrapper
            :disabled="$isDisabled"
            :valid="! $errors->has($statePath)"
            :attributes="
                \Filament\Support\prepare_inherited_attributes($attributes)
                    ->class(['fi-fo-record-select-input'])
            "
        >
            {{-- Single Value Mode --}}
            <template x-if="!allowMultiple">
                <button
                    type="button"
                    x-ref="trigger"
                    x-on:click.stop="togglePanel()"
                    x-on:keydown.enter.prevent="togglePanel()"
                    x-on:keydown.space.prevent="togglePanel()"
                    :disabled="isDisabled"
                    :aria-expanded="isOpen() ? 'true' : 'false'"
                    aria-haspopup="listbox"
                    :aria-controls="$id('panel')"
                    class="flex w-full min-h-[2.25rem] items-center gap-2 py-1.5 px-3 text-left focus:outline-none rounded"
                >
                    {{-- Selected Record or Placeholder --}}
                    <template x-if="hasValues && selectedRecords[0]">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <template x-if="selectedRecords[0].avatar">
                                <img
                                    :src="selectedRecords[0].avatar"
                                    :class="selectedRecords[0].avatarShape || 'rounded-full'"
                                    class="h-6 w-6 object-cover shrink-0"
                                    alt=""
                                />
                            </template>
                            <span class="text-sm text-gray-950 dark:text-white truncate" x-text="selectedRecords[0].label"></span>
                        </div>
                    </template>
                    <template x-if="!hasValues">
                        <span class="text-sm text-gray-400 dark:text-gray-500 flex-1">
                            {{ $emptyStateLabel }}
                        </span>
                    </template>

                    {{-- Clear button for single select --}}
                    <template x-if="hasValues && !isDisabled">
                        <button
                            type="button"
                            x-on:click.stop="state = []"
                            aria-label="Clear selection"
                            class="shrink-0 rounded p-0.5 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            <x-heroicon-m-x-mark class="size-4" />
                        </button>
                    </template>

                    {{-- Chevron --}}
                    <x-heroicon-m-chevron-down
                        class="size-4 text-gray-400 dark:text-gray-500 shrink-0 transition-transform duration-200"
                        x-bind:class="{ 'rotate-180': isOpen() }"
                    />
                </button>
            </template>

            {{-- Multiple Values Mode --}}
            <template x-if="allowMultiple">
                <div>
                    <button
                        type="button"
                        x-ref="trigger"
                        x-on:click.stop="togglePanel()"
                        x-on:keydown.enter.prevent="togglePanel()"
                        x-on:keydown.space.prevent="togglePanel()"
                        :disabled="isDisabled"
                        :aria-expanded="isOpen() ? 'true' : 'false'"
                        aria-haspopup="listbox"
                        :aria-controls="$id('panel')"
                        class="flex w-full min-h-[2.25rem] items-center gap-1.5 py-1.5 px-3 text-left focus:outline-none rounded"
                    >
                        {{-- Content area --}}
                        <div class="flex flex-1 items-center gap-1.5 flex-wrap overflow-hidden">
                            {{-- Empty State --}}
                            <template x-if="!hasValues">
                                <span class="text-sm text-gray-400 dark:text-gray-500">
                                    {{ $emptyStateLabel }}
                                </span>
                            </template>

                            {{-- Visible Records as Pills --}}
                            <template x-for="record in visibleRecords" :key="'pill-' + record.id">
                                <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                    <template x-if="record.avatar">
                                        <img
                                            :src="record.avatar"
                                            :class="record.avatarShape || 'rounded-full'"
                                            class="h-4 w-4 object-cover"
                                            alt=""
                                        />
                                    </template>
                                    <span x-text="record.label" class="truncate max-w-[100px]"></span>
                                    <button
                                        type="button"
                                        x-on:click.stop="removeRecord(record.id)"
                                        :aria-label="'Remove ' + record.label"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        :disabled="isDisabled"
                                    >
                                        <x-heroicon-m-x-mark class="size-3" />
                                    </button>
                                </span>
                            </template>

                            {{-- "+N more" indicator --}}
                            <template x-if="hiddenCount > 0">
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    +<span x-text="hiddenCount"></span> more
                                </span>
                            </template>
                        </div>

                        {{-- Chevron --}}
                        <x-heroicon-m-chevron-down
                            class="size-4 text-gray-400 dark:text-gray-500 shrink-0 transition-transform duration-200"
                            x-bind:class="{ 'rotate-180': isOpen() }"
                        />
                    </button>
                </div>
            </template>
        </x-filament::input.wrapper>

        {{-- Dropdown Panel --}}
        <div
            x-cloak
            x-float.placement.bottom-start.flip.offset="{ offset: 4 }"
            x-transition:enter-start="opacity-0"
            x-transition:leave-end="opacity-0"
            x-ref="panel"
            :id="$id('panel')"
            role="listbox"
            :aria-multiselectable="allowMultiple ? 'true' : 'false'"
            aria-label="Select records"
            class="absolute z-50 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 transition dark:bg-gray-900 dark:ring-white/10"
        >
            {{-- Search Input --}}
            <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100 dark:border-gray-800">
                <template x-if="!isSearching">
                    <x-heroicon-m-magnifying-glass class="size-4 text-gray-400 shrink-0" aria-hidden="true" />
                </template>
                <template x-if="isSearching">
                    <x-filament::loading-indicator class="size-4 text-gray-400 shrink-0" />
                </template>
                <input
                    type="text"
                    x-model.debounce.300ms="search"
                    x-ref="searchInput"
                    aria-label="Search records"
                    class="flex-1 bg-transparent border-0 p-0 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-0 focus:outline-none"
                    placeholder="{{ $placeholder }}"
                />
            </div>

            {{-- Options List --}}
            <div class="max-h-[280px] overflow-y-auto">
                <template x-if="sortedOptions.length === 0 && !isSearching && emptyStateMessage">
                    <div class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        <span x-text="emptyStateMessage"></span>
                    </div>
                </template>

                <template x-for="record in sortedOptions" :key="'option-' + record.id">
                    <button
                        type="button"
                        x-on:click.stop="allowMultiple ? toggleRecord(record) : selectRecord(record)"
                        :disabled="allowMultiple && !isSelected(record.id) && !canAddMore"
                        role="option"
                        :aria-selected="isSelected(record.id) ? 'true' : 'false'"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-gray-50 dark:hover:bg-white/5 focus:bg-gray-50 dark:focus:bg-white/5 focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                        :class="{ 'border-b border-gray-200 dark:border-gray-700': record.isLastSelected }"
                    >
                        {{-- Avatar (only shown when configured) --}}
                        <template x-if="record.avatar">
                            <img
                                :src="record.avatar"
                                :class="record.avatarShape || 'rounded-full'"
                                class="h-5 w-5 object-cover shrink-0"
                                alt=""
                            />
                        </template>

                        {{-- Label --}}
                        <span
                            class="flex-1 text-sm text-gray-700 dark:text-gray-200 truncate"
                            x-text="record.label"
                        ></span>

                        {{-- Checkbox (multi-select only) - Right side, hidden when limit reached for unselected --}}
                        <template x-if="allowMultiple && (isSelected(record.id) || canAddMore)">
                            <div class="shrink-0">
                                <div
                                    class="flex h-3.5 w-3.5 items-center justify-center rounded border transition-colors"
                                    :class="isSelected(record.id)
                                        ? 'border-primary-600 bg-primary-600 dark:border-primary-500 dark:bg-primary-500'
                                        : 'border-gray-300 dark:border-gray-600'"
                                >
                                    <template x-if="isSelected(record.id)">
                                        <svg class="h-2.5 w-2.5 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                        </svg>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Check icon for single-select --}}
                        <template x-if="!allowMultiple && isSelected(record.id)">
                            <x-heroicon-m-check class="size-4 text-primary-600 dark:text-primary-400 shrink-0" aria-hidden="true" />
                        </template>
                    </button>
                </template>
            </div>
        </div>
    </div>
</x-dynamic-component>
