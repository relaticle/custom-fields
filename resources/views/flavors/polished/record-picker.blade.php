@php
    use Relaticle\CustomFields\Data\RecordLinkPayload;

    $fieldWrapperView = $getFieldWrapperView();
    $isDisabled = $isDisabled();
    $statePath = $getStatePath();
    $allowMultiple = $getAllowMultiple();
    $maxValues = $getMaxValues();
    $maxVisiblePills = $getMaxVisiblePills();
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

    $chipClasses = 'fi-cf-record-chip inline-flex max-w-full items-center gap-1.5 rounded-md bg-gray-50 py-1 ps-1 pe-1.5 text-sm text-gray-950 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-white dark:ring-white/10';
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
        data-flavor="polished"
        data-surface="record-picker"
        x-data="{!! $pickerState !!}"
        x-on:click.outside="close()"
        x-on:keydown.esc="open && (close(), $event.stopPropagation())"
        x-on:keydown="onKeydown($event)"
        class="relative w-full"
    >
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
                class="flex min-h-[2.25rem] w-full items-center gap-1.5 rounded px-3 py-1.5 text-left focus:outline-none"
            >
                <div class="flex flex-1 flex-wrap items-center gap-1.5 overflow-hidden">
                    <template x-if="!hasValues">
                        <span class="text-sm text-gray-400 dark:text-gray-500">{{ $emptyStateLabel }}</span>
                    </template>

                    <template x-for="(record, index) in visibleRecords" :key="'chip-' + record.id">
                        <span
                            class="{{ $chipClasses }}"
                            :title="record.provenance"
                            :data-provenance="record.provenance"
                        >
                            <template x-if="record.avatar">
                                <img :src="record.avatar" :class="record.avatarShape || 'rounded-full'" class="h-5 w-5 shrink-0 object-cover" alt="" />
                            </template>
                            <template x-if="!record.avatar">
                                <span
                                    aria-hidden="true"
                                    class="flex h-5 w-5 shrink-0 items-center justify-center bg-gray-200 text-[0.625rem] font-semibold uppercase text-gray-600 dark:bg-white/10 dark:text-gray-300"
                                    :class="record.avatarShape || 'rounded-full'"
                                    x-text="(record.label || '').slice(0, 1)"
                                ></span>
                            </template>
                            <span class="max-w-[10rem] truncate" x-text="record.label"></span>

                            @include('custom-fields::forms.partials.record-move-buttons')

                            <button
                                type="button"
                                x-on:click.stop="removeRecord(record.id)"
                                :aria-label="@js(__('custom-fields::custom-fields.record.remove', ['record' => ':record'])).replace(':record', record.label)"
                                :disabled="isDisabled"
                                class="rounded p-0.5 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:hover:text-gray-200"
                            >
                                <x-filament::icon icon="heroicon-m-x-mark" class="size-3" />
                            </button>
                        </span>
                    </template>

                    <template x-if="hiddenCount > 0">
                        <span
                            class="text-xs text-gray-500 dark:text-gray-400"
                            x-text="countLabel(overflowLabels, hiddenCount)"
                        ></span>
                    </template>
                </div>

                <x-filament::icon
                    icon="heroicon-m-chevron-down"
                    class="size-4 shrink-0 text-gray-400 transition-transform duration-200 dark:text-gray-500"
                    x-bind:class="{ 'rotate-180': open }"
                />
            </button>
        </x-filament::input.wrapper>

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
            <div class="flex items-center gap-2 border-b border-gray-100 px-3 py-2 dark:border-gray-800">
                <template x-if="!isSearching">
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="size-4 shrink-0 text-gray-400" aria-hidden="true" />
                </template>
                <template x-if="isSearching">
                    <x-filament::loading-indicator class="size-4 shrink-0 text-gray-400" />
                </template>
                <input
                    type="text"
                    x-model.debounce.300ms="search"
                    x-ref="searchInput"
                    x-on:keydown="onSearchKeydown($event)"
                    aria-label="{{ __('custom-fields::custom-fields.record.search_label') }}"
                    :aria-controls="$id('panel')"
                    :aria-activedescendant="activeDescendant"
                    class="flex-1 border-0 bg-transparent p-0 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0 dark:text-gray-100 dark:placeholder:text-gray-500"
                    placeholder="{{ $placeholder }}"
                />
            </div>

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
                        class="flex w-full items-center gap-2 px-3 py-2 text-left transition-colors focus:outline-none disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent"
                        :class="activeIndex === index ? 'bg-gray-100 dark:bg-gray-800' : 'hover:bg-gray-50 dark:hover:bg-white/5'"
                    >
                        <template x-if="record.avatar">
                            <img :src="record.avatar" :class="record.avatarShape || 'rounded-full'" class="h-5 w-5 shrink-0 object-cover" alt="" />
                        </template>
                        <template x-if="!record.avatar">
                            <span
                                aria-hidden="true"
                                class="flex h-5 w-5 shrink-0 items-center justify-center bg-gray-200 text-[0.625rem] font-semibold uppercase text-gray-600 dark:bg-white/10 dark:text-gray-300"
                                :class="record.avatarShape || 'rounded-full'"
                                x-text="(record.label || '').slice(0, 1)"
                            ></span>
                        </template>

                        <span class="flex-1 truncate text-sm text-gray-700 dark:text-gray-200" x-text="record.label"></span>

                        <template x-if="isSelected(record.id)">
                            <x-filament::icon icon="heroicon-m-check" class="size-4 shrink-0 text-primary-600 dark:text-primary-400" aria-hidden="true" />
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
