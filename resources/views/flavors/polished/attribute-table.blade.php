@php
    $pairs = $this->relationshipPairs;
    $activeFields = $this->activeFields;
    $inactiveFields = $this->inactiveFields;
    $isEmpty = $activeFields->count() === 0 && $inactiveFields->count() === 0;
@endphp

<div data-flavor="polished" data-surface="attribute-table" class="flex flex-col gap-4">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="max-w-sm flex-1">
            <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    :placeholder="__('custom-fields::custom-fields.field.form.search_placeholder')"
                />
            </x-filament::input.wrapper>
        </div>

        {{ $this->createFieldAction() }}
    </div>

    <div class="fi-ta overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div
            wire:loading.delay
            wire:target="search"
            class="fi-cf-attribute-skeleton flex flex-col divide-y divide-gray-100 dark:divide-white/5"
            aria-hidden="true"
        >
            @for ($row = 0; $row < 3; $row++)
                <div class="flex items-center gap-3 px-4 py-4">
                    <span class="h-4 w-4 shrink-0 animate-pulse rounded bg-gray-100 dark:bg-white/10"></span>
                    <span class="h-4 w-1/3 animate-pulse rounded bg-gray-100 dark:bg-white/10"></span>
                    <span class="h-4 w-24 animate-pulse rounded bg-gray-100 dark:bg-white/10"></span>
                </div>
            @endfor
        </div>

        <div wire:loading.remove.delay wire:target="search">
            @unless ($isEmpty)
                <div class="overflow-x-auto">
                    <div class="min-w-[640px]">
                        <div class="grid grid-cols-[40px_minmax(0,1fr)_minmax(120px,180px)_minmax(120px,1fr)_50px] border-b border-gray-200 bg-gray-50 text-sm font-semibold text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">
                            <div></div>
                            <div class="px-3 py-3">{{ __('custom-fields::custom-fields.field.form.name') }}</div>
                            <div class="px-3 py-3">{{ __('custom-fields::custom-fields.field.form.type') }}</div>
                            <div class="px-3 py-3">{{ __('custom-fields::custom-fields.common.properties') }}</div>
                            <div></div>
                        </div>

                        <div
                            x-sortable
                            wire:end.stop="updateFieldsOrder($event.target.sortable.toArray())"
                            data-sortable-animation-duration="300"
                            class="divide-y divide-gray-100 dark:divide-white/5"
                        >
                            @foreach ($activeFields as $field)
                                @include('custom-fields::flavors.polished.partials.attribute-row', [
                                    'field' => $field,
                                    'pair' => $pairs[(string) $field->getKey()] ?? null,
                                    'sortable' => true,
                                ])
                            @endforeach
                        </div>

                        @if ($inactiveFields->count())
                            <div
                                x-data="{ open: {{ filled($search) ? 'true' : 'false' }} }"
                                x-init="$watch('$wire.search', value => open = value.length > 0)"
                                class="border-t border-gray-200 dark:border-white/10"
                            >
                                <button
                                    type="button"
                                    x-on:click="open = ! open"
                                    :aria-expanded="open ? 'true' : 'false'"
                                    class="grid w-full grid-cols-[40px_minmax(0,1fr)] items-center text-left transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5"
                                >
                                    <div class="flex items-center justify-center py-3">
                                        <x-filament::icon
                                            icon="heroicon-m-chevron-up"
                                            class="h-4 w-4 text-gray-400 transition-transform duration-200"
                                            x-bind:class="{ 'rotate-180': ! open }"
                                        />
                                    </div>
                                    <div class="px-3 py-3 text-sm font-medium text-gray-500 dark:text-gray-400">
                                        {{ __('custom-fields::custom-fields.common.archived') }}
                                        <span class="text-gray-400 dark:text-gray-500">({{ $inactiveFields->count() }})</span>
                                    </div>
                                </button>

                                <div x-show="open" x-collapse class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($inactiveFields as $field)
                                        @include('custom-fields::flavors.polished.partials.attribute-row', [
                                            'field' => $field,
                                            'pair' => $pairs[(string) $field->getKey()] ?? null,
                                            'sortable' => false,
                                        ])
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <div class="px-6 py-12">
                    <div class="mx-auto grid max-w-md justify-items-center text-center">
                        <div class="mb-4 rounded-full bg-gray-100 p-3 dark:bg-white/10">
                            <x-filament::icon
                                :icon="filled($search) ? 'heroicon-m-magnifying-glass' : 'heroicon-o-squares-plus'"
                                class="h-6 w-6 text-gray-400 dark:text-gray-500"
                            />
                        </div>
                        <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                            {{ filled($search)
                                ? __('custom-fields::custom-fields.empty_states.search_no_results.heading')
                                : __('custom-fields::custom-fields.empty_states.fields_no_sections.heading') }}
                        </h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ filled($search)
                                ? __('custom-fields::custom-fields.empty_states.search_no_results.description')
                                : __('custom-fields::custom-fields.empty_states.fields_no_sections.description') }}
                        </p>

                        @unless (filled($search))
                            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('custom-fields::custom-fields.empty_states.fields_no_sections.education') }}
                            </p>
                        @endunless
                    </div>
                </div>
            @endunless
        </div>
    </div>

    <x-filament-actions::modals/>
</div>
