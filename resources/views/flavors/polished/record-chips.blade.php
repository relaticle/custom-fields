@php
    /** @var array<int, array{id: string, name: string, avatarUrl: ?string, avatarShape: string, url: ?string, provenance: ?string}> $chips */
    $maxVisible = $maxVisible ?? count($chips);
    $visibleChips = array_slice($chips, 0, max(1, $maxVisible));
    $hiddenChips = array_slice($chips, count($visibleChips));
@endphp

<div
    data-flavor="polished"
    data-surface="record-chips"
    class="fi-cf-record-chips flex flex-wrap items-center gap-1.5"
>
    @forelse ($visibleChips as $chip)
        @include('custom-fields::flavors.polished.partials.record-chip', ['chip' => $chip])
    @empty
        <span class="fi-cf-record-chips-empty text-sm text-gray-400 dark:text-gray-500">
            {{ __('custom-fields::custom-fields.record.no_records') }}
        </span>
    @endforelse

    @if ($hiddenChips !== [])
        <div
            class="relative"
            x-data="{ expanded: false }"
            x-on:keydown.escape="expanded = false"
        >
            <button
                type="button"
                x-on:click.stop="expanded = ! expanded"
                :aria-expanded="expanded ? 'true' : 'false'"
                aria-haspopup="true"
                class="fi-cf-record-chips-overflow inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/20"
            >
                {{ trans_choice('custom-fields::custom-fields.record.more_records', count($hiddenChips), ['count' => count($hiddenChips)]) }}
            </button>

            <div
                x-cloak
                x-show="expanded"
                x-on:click.outside="expanded = false"
                x-transition
                class="absolute z-50 mt-1 max-h-[280px] w-[240px] overflow-y-auto rounded-lg bg-white p-1.5 shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="flex flex-col items-start gap-1">
                    @foreach ($hiddenChips as $chip)
                        @include('custom-fields::flavors.polished.partials.record-chip', ['chip' => $chip])
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
