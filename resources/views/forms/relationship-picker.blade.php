@php
    use Relaticle\CustomFields\Data\RecordLinkPayload;

    $fieldWrapperView = $getFieldWrapperView();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $allowMultiple = $getAllowMultiple();
    $maxValues = $getMaxValues();
    $maxVisiblePills = $getMaxVisiblePills();
    $addLabel = $getAddLabel();
    $emptyStateLabel = $getEmptyStateLabel();
    $placeholder = $getPlaceholder() ?? __('custom-fields::custom-fields.record.search_placeholder');
    $key = $getKey();
    $minSearchLength = $getMinSearchLength();
    $shortSearchMessage = __('custom-fields::custom-fields.record.short_search', ['count' => $minSearchLength]);
    $checksHolderConflicts = $checksHolderConflicts();
    $createUrl = $getCreateUrl();
    $createLabel = $getCreateLabel();

    // A confirmed move travels as a map naming the record it was given for, so a failed
    // validation round trip brings back that record and not whichever one sorts first.
    $state = $getState() ?? [];
    $selectedIds = array_filter(is_array($state) ? ($state['ids'] ?? $state) : []);
    $confirmedStealIds = RecordLinkPayload::confirmedIds(is_array($state) ? $state : [], $selectedIds);
    // A pluralized key cannot be read by __(), so both forms are chosen server-side and the
    // client picks between them by count.
    $overflowLabels = [
        'one' => trans_choice('custom-fields::custom-fields.record.more_records', 1, ['count' => ':count']),
        'many' => trans_choice('custom-fields::custom-fields.record.more_records', 2, ['count' => ':count']),
    ];
    $countLabels = [
        'one' => trans_choice('custom-fields::custom-fields.record.announce_count', 1, ['count' => ':count']),
        'many' => trans_choice('custom-fields::custom-fields.record.announce_count', 2, ['count' => ':count']),
    ];
    $initialRecords = $getRecordsByIds($selectedIds);
    $initialOptions = $getInitialOptions();
    $pickerState = view('custom-fields::forms.partials.record-select-state', [
        'applyStateBindingModifiers' => $applyStateBindingModifiers,
        'statePath' => $statePath,
        'key' => $key,
        'allowMultiple' => $allowMultiple,
        'maxValues' => $maxValues,
        'isDisabled' => $isDisabled,
        'initialRecords' => $initialRecords,
        'initialOptions' => $initialOptions,
        'maxVisiblePills' => $maxVisiblePills,
        'minSearchLength' => $minSearchLength,
        'shortSearchMessage' => $shortSearchMessage,
        'checksHolderConflicts' => $checksHolderConflicts,
        'confirmedStealIds' => $confirmedStealIds,
        'overflowLabels' => $overflowLabels,
        'countLabels' => $countLabels,
    ])->render();
@endphp

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    class="fi-fo-record-select-input-wrp"
>
    <div
        wire:key="{{ $key }}-{{ $isDisabled ? 'disabled' : 'enabled' }}"
        wire:ignore.self
        x-cloak
        x-data="{!! $pickerState !!}"
        x-on:click.outside="close()"
        x-on:keydown.esc="open && (close(), $event.stopPropagation())"
        x-on:keydown="onKeydown($event)"
        class="relative w-full"
    >
        {{-- Hidden live region for screen reader announcements --}}
        <div x-ref="announcer" aria-live="polite" aria-atomic="true" class="sr-only"></div>

        <x-filament::input.wrapper
            :disabled="$isDisabled"
            :valid="! $errors->has($statePath)"
            :attributes="
                \Filament\Support\prepare_inherited_attributes($attributes)
                    ->class(['fi-fo-record-select-input'])
            "
            x-bind:class="{ 'ring-2 ring-primary-600 dark:ring-primary-500': open }"
        >
            {{-- Single Value Mode --}}
            <template x-if="!allowMultiple">
                <button
                    type="button"
                    x-ref="trigger"
                    x-on:click="toggle()"
                    x-on:keydown.enter.prevent="toggle()"
                    x-on:keydown.space.prevent="toggle()"
                    :disabled="isDisabled"
                    role="combobox"
                    :aria-expanded="open ? 'true' : 'false'"
                    aria-haspopup="listbox"
                    :aria-controls="$id('panel')"
                    :aria-activedescendant="activeDescendant"
                    class="flex w-full min-h-[2.25rem] items-center gap-2 py-1.5 px-3 text-left focus:outline-none rounded"
                >
                    {{-- Selected Record or Placeholder --}}
                    <template x-if="hasValues && selectedRecords[0]">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <template x-if="selectedRecords[0].avatar">
                                <img
                                    :src="selectedRecords[0].avatar"
                                    :class="selectedRecords[0].avatarShape || 'rounded-full'"
                                    class="h-5 w-5 object-cover shrink-0"
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
                            aria-label="{{ __('custom-fields::custom-fields.record.clear') }}"
                            class="shrink-0 rounded p-0.5 text-gray-400 hover:text-gray-500 dark:hover:text-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500"
                        >
                            <x-filament::icon icon="heroicon-m-x-mark" class="size-4" />
                        </button>
                    </template>

                    {{-- Chevron --}}
                    <x-filament::icon icon="heroicon-m-chevron-down"
                        class="size-4 text-gray-400 dark:text-gray-500 shrink-0 transition-transform duration-200"
                        x-bind:class="{ 'rotate-180': open }"
                    />
                </button>
            </template>

            {{-- Multiple Values Mode --}}
            <template x-if="allowMultiple">
                <div>
                    <button
                        type="button"
                        x-ref="trigger"
                        x-on:click="toggle()"
                        x-on:keydown.enter.prevent="toggle()"
                        x-on:keydown.space.prevent="toggle()"
                        :disabled="isDisabled"
                        role="combobox"
                        :aria-expanded="open ? 'true' : 'false'"
                        aria-haspopup="listbox"
                        :aria-controls="$id('panel')"
                        :aria-activedescendant="activeDescendant"
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
                            <template x-for="(record, index) in visibleRecords" :key="'pill-' + record.id">
                                <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-200">
                                    <template x-if="record.avatar">
                                        <img
                                            :src="record.avatar"
                                            :class="record.avatarShape || 'rounded-full'"
                                            class="h-5 w-5 object-cover"
                                            alt=""
                                        />
                                    </template>
                                    <span x-text="record.label" class="truncate max-w-[100px]"></span>

                                    @include('custom-fields::forms.partials.record-move-buttons')

                                    <button
                                        type="button"
                                        x-on:click.stop="removeRecord(record.id)"
                                        :aria-label="@js(__('custom-fields::custom-fields.record.remove', ['record' => ':record'])).replace(':record', record.label)"
                                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                                        :disabled="isDisabled"
                                    >
                                        <x-filament::icon icon="heroicon-m-x-mark" class="size-3" />
                                    </button>
                                </span>
                            </template>

                            {{-- Overflow indicator --}}
                            <template x-if="hiddenCount > 0">
                                <span
                                    class="text-xs text-gray-500 dark:text-gray-400"
                                    x-text="countLabel(overflowLabels, hiddenCount)"
                                ></span>
                            </template>
                        </div>

                        {{-- Chevron --}}
                        <x-filament::icon icon="heroicon-m-chevron-down"
                            class="size-4 text-gray-400 dark:text-gray-500 shrink-0 transition-transform duration-200"
                            x-bind:class="{ 'rotate-180': open }"
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
            aria-label="{{ __('custom-fields::custom-fields.record.select_label') }}"
            class="absolute z-50 w-full overflow-hidden rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 transition dark:bg-gray-900 dark:ring-white/10"
        >
            {{-- Search Input --}}
            <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100 dark:border-gray-800">
                <template x-if="!isSearching">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="size-4 text-gray-400 shrink-0" aria-hidden="true" />
                </template>
                <template x-if="isSearching">
                    <x-filament::loading-indicator class="size-4 text-gray-400 shrink-0" />
                </template>
                <input
                    type="text"
                    x-model.debounce.300ms="search"
                    x-ref="searchInput"
                    x-on:keydown="onSearchKeydown($event)"
                    aria-label="{{ __('custom-fields::custom-fields.record.search_label') }}"
                    :aria-controls="$id('panel')"
                    :aria-activedescendant="activeDescendant"
                    class="flex-1 bg-transparent border-0 p-0 text-sm text-gray-900 dark:text-gray-100 placeholder:text-gray-400 dark:placeholder:text-gray-500 focus:ring-0 focus:outline-none"
                    placeholder="{{ $placeholder }}"
                />
            </div>

            {{-- A record another holder already has moves only on confirmation --}}
            <template x-if="pendingSteal">
                <div class="border-b border-warning-200 bg-warning-50 px-3 py-2 text-sm dark:border-warning-400/20 dark:bg-warning-400/10">
                    <p class="font-medium text-warning-800 dark:text-warning-200">{{ __('custom-fields::custom-fields.record.steal_heading') }}</p>
                    <p class="mt-1 text-warning-700 dark:text-warning-300" x-text="pendingSteal.message"></p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" x-on:click.stop="confirmSteal()" class="rounded-md bg-warning-600 px-2 py-1 text-xs font-medium text-white hover:bg-warning-500">
                            {{ __('custom-fields::custom-fields.record.steal_confirm') }}
                        </button>
                        <button type="button" x-on:click.stop="cancelSteal()" class="rounded-md px-2 py-1 text-xs font-medium text-warning-800 hover:bg-warning-100 dark:text-warning-200 dark:hover:bg-warning-400/20">
                            {{ __('custom-fields::custom-fields.record.steal_cancel') }}
                        </button>
                    </div>
                </div>
            </template>

            {{-- Options List --}}
            <div x-ref="optionsList" class="max-h-[280px] overflow-y-auto">
                <template x-if="sortedOptions.length === 0 && !isSearching && emptyStateMessage">
                    <div class="px-3 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                        <span x-text="emptyStateMessage"></span>
                    </div>
                </template>

                <template x-for="(record, index) in sortedOptions" :key="'option-' + record.id">
                    <button
                        type="button"
                        :id="$id('option-' + index)"
                        x-on:click.stop="allowMultiple ? toggleRecord(record) : selectRecord(record)"
                        x-on:mouseenter="activeIndex = index"
                        :disabled="allowMultiple && !isSelected(record.id) && !canAddMore"
                        role="option"
                        :aria-selected="isSelected(record.id) ? 'true' : 'false'"
                        :data-highlighted="activeIndex === index ? '' : undefined"
                        class="flex w-full items-center gap-2 px-3 py-2 text-left transition-colors focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-transparent"
                        :class="activeIndex === index
                            ? 'bg-gray-100 dark:bg-gray-800'
                            : 'hover:bg-gray-50 dark:hover:bg-white/5'"
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
                            <x-filament::icon icon="heroicon-m-check" class="size-4 text-primary-600 dark:text-primary-400 shrink-0" aria-hidden="true" />
                        </template>
                    </button>
                </template>
            </div>

            @if (filled($createUrl))
                <a
                    href="{{ $createUrl }}"
                    class="flex items-center gap-2 border-t border-gray-100 px-3 py-2 text-sm text-primary-600 hover:bg-gray-50 dark:border-gray-800 dark:text-primary-400 dark:hover:bg-white/5"
                >
                    <x-filament::icon icon="heroicon-m-plus" class="size-4 shrink-0" aria-hidden="true" />
                    {{ $createLabel }}
                </a>
            @endif
        </div>
    </div>
</x-dynamic-component>
