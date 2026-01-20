@php
    $currentRecord = $getRecord();
    $records = $getRecords($currentRecord);
    $multiple = $isMultiple();
    $maxVisible = 1;
    $visibleRecords = array_slice($records, 0, $maxVisible);
    $hiddenCount = max(0, count($records) - $maxVisible);
@endphp

<div
    x-data="{
        isOpen() {
            return this.$refs.panel?._x_isShown === true;
        },
        togglePanel() {
            this.$refs.panel?.toggle(this.$refs.trigger);
        },
        closePanel() {
            this.$refs.panel?.close();
        }
    }"
    x-on:keydown.esc="isOpen() && (closePanel(), $event.stopPropagation())"
    x-on:click.outside="closePanel()"
    class="relative"
>
    @if (empty($records))
        <span class="text-gray-400 dark:text-gray-500">&mdash;</span>
    @elseif ($multiple)
        {{-- Multiple records display --}}
        <div
            @if ($hiddenCount > 0)
                x-ref="trigger"
                role="button"
                tabindex="0"
                :aria-expanded="isOpen() ? 'true' : 'false'"
                aria-haspopup="menu"
                :aria-controls="$id('panel')"
                x-on:click.stop="togglePanel()"
                x-on:keydown.enter.prevent="togglePanel()"
                x-on:keydown.space.prevent="togglePanel()"
            @endif
            class="flex items-center gap-x-2 text-left overflow-hidden {{ $hiddenCount > 0 ? 'cursor-pointer' : '' }}"
        >
            @foreach ($visibleRecords as $record)
                @if ($record['url'])
                    <a
                        href="{{ $record['url'] }}"
                        x-on:click.stop
                        class="inline-flex items-center gap-2 py-0.5 px-1 -mx-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors shrink-0"
                    >
                        @if ($record['avatarUrl'])
                            <img
                                src="{{ $record['avatarUrl'] }}"
                                alt=""
                                class="h-5 w-5 {{ $record['avatarShape'] }} object-cover shrink-0"
                            />
                        @endif
                        <span class="text-sm text-gray-950 dark:text-white underline decoration-gray-300 dark:decoration-gray-600 underline-offset-2 truncate max-w-[120px]">{{ $record['name'] }}</span>
                    </a>
                @else
                    <span class="inline-flex items-center gap-2 py-0.5 px-1 shrink-0">
                        @if ($record['avatarUrl'])
                            <img
                                src="{{ $record['avatarUrl'] }}"
                                alt=""
                                class="h-5 w-5 {{ $record['avatarShape'] }} object-cover shrink-0"
                            />
                        @endif
                        <span class="text-sm text-gray-950 dark:text-white truncate max-w-[120px]">{{ $record['name'] }}</span>
                    </span>
                @endif
            @endforeach
            @if ($hiddenCount > 0)
                <span class="text-sm text-gray-400 dark:text-gray-500 whitespace-nowrap shrink-0" aria-hidden="true">+{{ $hiddenCount }}</span>
                <span class="sr-only">and {{ $hiddenCount }} more. Press Enter to view all.</span>
            @endif
        </div>

        {{-- Expanded Popover - All records --}}
        @if ($hiddenCount > 0)
            <div
                x-cloak
                x-float.placement.bottom-start.flip.offset.teleport="{ offset: 4 }"
                x-transition:enter-start="opacity-0"
                x-transition:leave-end="opacity-0"
                x-ref="panel"
                role="menu"
                :id="$id('panel')"
                aria-label="All linked records"
                class="absolute z-50 w-[220px] rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 transition dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="py-1 max-h-[280px] overflow-y-auto">
                    @foreach ($records as $record)
                        <div
                            role="menuitem"
                            class="px-2 py-1 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                        >
                            @if ($record['url'])
                                <a
                                    href="{{ $record['url'] }}"
                                    x-on:click.stop
                                    class="inline-flex items-center gap-2 py-0.5 px-1 -mx-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors w-full"
                                >
                                    @if ($record['avatarUrl'])
                                        <img
                                            src="{{ $record['avatarUrl'] }}"
                                            alt=""
                                            class="h-5 w-5 {{ $record['avatarShape'] }} object-cover shrink-0"
                                        />
                                    @endif
                                    <span class="text-sm text-gray-950 dark:text-white underline decoration-gray-300 dark:decoration-gray-600 underline-offset-2 truncate">{{ $record['name'] }}</span>
                                </a>
                            @else
                                <div class="inline-flex items-center gap-2 py-0.5 px-1 -mx-1 w-full">
                                    @if ($record['avatarUrl'])
                                        <img
                                            src="{{ $record['avatarUrl'] }}"
                                            alt=""
                                            class="h-5 w-5 {{ $record['avatarShape'] }} object-cover shrink-0"
                                        />
                                    @endif
                                    <span class="text-sm text-gray-950 dark:text-white truncate">{{ $record['name'] }}</span>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @else
        {{-- Single record display --}}
        @php
            $record = $records[0];
        @endphp
        @if ($record['url'])
            <a
                href="{{ $record['url'] }}"
                x-on:click.stop
                class="inline-flex items-center gap-2 py-0.5 px-1 -mx-1 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
            >
                @if ($record['avatarUrl'])
                    <img
                        src="{{ $record['avatarUrl'] }}"
                        alt=""
                        class="h-5 w-5 {{ $record['avatarShape'] }} object-cover shrink-0"
                    />
                @endif
                <span class="text-sm text-gray-950 dark:text-white underline decoration-gray-300 dark:decoration-gray-600 underline-offset-2 truncate">{{ $record['name'] }}</span>
            </a>
        @else
            <div class="inline-flex items-center gap-2">
                @if ($record['avatarUrl'])
                    <img
                        src="{{ $record['avatarUrl'] }}"
                        alt=""
                        class="h-5 w-5 {{ $record['avatarShape'] }} object-cover shrink-0"
                    />
                @endif
                <span class="text-sm text-gray-950 dark:text-white truncate">{{ $record['name'] }}</span>
            </div>
        @endif
    @endif
</div>
