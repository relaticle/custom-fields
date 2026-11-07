@if ($record['avatarUrl'])
    <img
        src="{{ $record['avatarUrl'] }}"
        alt=""
        class="h-5 w-5 {{ $record['avatarShape'] }} object-cover shrink-0"
    />
@elseif ($record['icon'])
    <span
        data-record-icon
        class="flex h-5 w-5 {{ $record['avatarShape'] }} shrink-0 items-center justify-center bg-gray-100 text-gray-600 ring-1 ring-gray-950/5 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10"
    >
        <x-filament::icon :icon="$record['icon']" class="h-3 w-3" />
    </span>
@endif
