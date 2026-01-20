@php
    $state = $getState();
    $records = $state['records'] ?? [];
    $multiple = $state['multiple'] ?? false;
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="flex flex-wrap gap-x-4 gap-y-2">
        @forelse ($records as $record)
            @if ($record['url'])
                <a href="{{ $record['url'] }}"
                   x-on:click.stop
                   class="inline-flex items-center gap-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors py-0.5 px-1 -mx-1">
                    @if ($record['avatarUrl'])
                        <img src="{{ $record['avatarUrl'] }}" alt="" class="h-5 w-5 {{ $record['avatarShape'] }} object-cover shrink-0" />
                    @endif
                    <span class="text-sm text-gray-950 dark:text-white underline decoration-gray-300 dark:decoration-gray-600 underline-offset-2">{{ $record['name'] }}</span>
                </a>
            @else
                <div class="inline-flex items-center gap-2">
                    @if ($record['avatarUrl'])
                        <img src="{{ $record['avatarUrl'] }}" alt="" class="h-5 w-5 {{ $record['avatarShape'] }} object-cover shrink-0" />
                    @endif
                    <span class="text-sm text-gray-950 dark:text-white">{{ $record['name'] }}</span>
                </div>
            @endif
        @empty
            <span class="text-gray-400 dark:text-gray-500">—</span>
        @endforelse
    </div>
</x-dynamic-component>
