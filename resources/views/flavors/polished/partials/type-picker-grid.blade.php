@php
    $searchIndex = array_map(
        static fn (array $choice): array => [
            'key' => $choice['key'],
            'haystack' => mb_strtolower($choice['label'].' '.($choice['description'] ?? '')),
        ],
        $choices,
    );
@endphp

<div
    data-flavor="polished"
    data-surface="type-picker"
    x-data="{
        state: $wire.{!! $stateBinding !!},
        search: '',
        isDisabled: @js($isDisabled),
        index: @js($searchIndex),

        get term() {
            return this.search.trim().toLowerCase();
        },

        shows(key) {
            if (this.term === '') {
                return true;
            }

            const entry = this.index.find((choice) => choice.key === key);

            return entry ? entry.haystack.includes(this.term) : false;
        },

        get matchCount() {
            return this.index.filter((choice) => this.shows(choice.key)).length;
        },

        select(key) {
            if (this.isDisabled) {
                return;
            }

            this.state = key;
        },
    }"
    class="fi-cf-type-picker flex flex-col gap-3"
>
    @if ($isDisabled)
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('custom-fields::custom-fields.field_type_picker.locked') }}
        </p>
    @else
        <x-filament::input.wrapper prefix-icon="heroicon-m-magnifying-glass">
            <x-filament::input
                type="search"
                x-model.debounce.200ms="search"
                :placeholder="__('custom-fields::custom-fields.field_type_picker.search_placeholder')"
            />
        </x-filament::input.wrapper>
    @endif

    <div
        role="radiogroup"
        aria-label="{{ $label }}"
        class="grid max-h-[22rem] grid-cols-1 gap-2 overflow-y-auto sm:grid-cols-2 lg:grid-cols-3"
    >
        @foreach ($choices as $choice)
            <button
                type="button"
                role="radio"
                data-choice="{{ $choice['key'] }}"
                x-show="shows(@js($choice['key']))"
                :aria-checked="state === @js($choice['key']) ? 'true' : 'false'"
                :data-selected="state === @js($choice['key']) ? '' : undefined"
                @disabled($isDisabled)
                x-on:click="select(@js($choice['key']))"
                x-on:keydown.enter.prevent="select(@js($choice['key']))"
                x-on:keydown.space.prevent="select(@js($choice['key']))"
                class="fi-cf-type-picker-choice flex items-start gap-2 rounded-xl border p-3 text-start transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                :class="state === @js($choice['key'])
                    ? 'border-primary-600 bg-primary-50 dark:border-primary-400 dark:bg-primary-400/10'
                    : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:hover:bg-white/10'"
            >
                <x-filament::icon
                    :icon="$choice['icon']"
                    class="mt-0.5 h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500"
                    aria-hidden="true"
                />

                <span class="min-w-0">
                    <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">{{ $choice['label'] }}</span>

                    @if (filled($choice['description']))
                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $choice['description'] }}</span>
                    @endif
                </span>
            </button>
        @endforeach
    </div>

    <p
        x-cloak
        x-show="matchCount === 0"
        class="rounded-xl bg-gray-50 px-3 py-6 text-center text-sm text-gray-500 dark:bg-white/5 dark:text-gray-400"
    >
        {{ __('custom-fields::custom-fields.field_type_picker.no_results') }}
    </p>
</div>
