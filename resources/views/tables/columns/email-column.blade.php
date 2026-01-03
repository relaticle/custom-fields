@php
    $state = $getState();
    $entries = is_array($state) ? array_values(array_filter($state, fn($e) => !empty($e))) : [];
    $maxVisible = 1;
    $visibleEntries = array_slice($entries, 0, $maxVisible);
    $hiddenCount = max(0, count($entries) - $maxVisible);
@endphp

<div
    x-data="{
        isOpen: false,
        copiedIndex: null,
        toggle() {
            this.isOpen = !this.isOpen;
        },
        close() {
            this.isOpen = false;
        },
        copyToClipboard(text, index, event) {
            event.preventDefault();
            event.stopPropagation();
            window.navigator.clipboard.writeText(text);
            this.copiedIndex = index;
            setTimeout(() => {
                this.copiedIndex = null;
            }, 2000);
        }
    }"
    x-on:click.outside="close()"
    class="relative"
>
    @if (empty($entries))
        <span class="text-gray-400 dark:text-gray-500">—</span>
    @else
        {{-- Collapsed View - Single line with truncated values --}}
        <div
            class="flex items-center gap-x-3 text-left cursor-pointer overflow-hidden"
            x-on:click="toggle()"
        >
            @foreach ($visibleEntries as $index => $entry)
                <div class="group/value relative inline-flex items-center py-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors shrink-0">
                    <a
                        href="mailto:{{ $entry }}"
                        x-on:click.stop
                        class="text-sm text-primary-600 dark:text-primary-400 underline decoration-gray-300 dark:decoration-gray-600 decoration-1 underline-offset-2 truncate max-w-[120px]"
                    >{{ $entry }}</a>
                    <button
                        type="button"
                        x-on:click.stop="copyToClipboard(@js($entry), 'visible-{{ $index }}', $event)"
                        class="absolute right-0 opacity-0 group-hover/value:opacity-100 transition-opacity duration-300 py-0.5 pl-2 pr-1 rounded-r bg-gradient-to-r from-gray-100/90 via-gray-100/100 to-gray-100 dark:from-gray-700/0 dark:via-gray-700/70 dark:to-gray-700"
                    >
                        <x-heroicon-m-clipboard-document
                            x-show="copiedIndex !== 'visible-{{ $index }}'"
                            class="size-3.5 text-primary-500"
                            aria-hidden="true"
                        />
                        <x-heroicon-m-check
                            x-show="copiedIndex === 'visible-{{ $index }}'"
                            x-cloak
                            class="size-3.5 text-green-500"
                            aria-hidden="true"
                        />
                    </button>
                </div>
            @endforeach
            @if ($hiddenCount > 0)
                <span class="text-sm text-gray-400 dark:text-gray-500 whitespace-nowrap">+{{ $hiddenCount }}</span>
            @endif
        </div>

        {{-- Expanded Popover - All values, uses floating UI --}}
        @if ($hiddenCount > 0)
            <div
                x-cloak
                x-show="isOpen"
                x-float.placement.bottom-start.flip.teleport.offset="{ offset: 4 }"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="z-50 w-[180px] rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="py-1 max-h-[280px] overflow-y-auto overflow-x-auto">
                    @foreach ($entries as $index => $entry)
                        <div class="flex items-center px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <div class="group/value relative inline-flex items-center py-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <a
                                    href="mailto:{{ $entry }}"
                                    x-on:click.stop
                                    class="text-sm text-primary-600 dark:text-primary-400 underline decoration-gray-300 dark:decoration-gray-600 decoration-1 underline-offset-2"
                                >{{ $entry }}</a>
                                <button
                                    type="button"
                                    x-on:click.stop="copyToClipboard(@js($entry), {{ $index }}, $event)"
                                    class="absolute right-0 opacity-0 group-hover/value:opacity-100 transition-opacity duration-300 py-0.5 pl-2 pr-1 rounded-r bg-gradient-to-r from-gray-100/90 via-gray-100/100 to-gray-100 dark:from-gray-700/0 dark:via-gray-700/70 dark:to-gray-700"
                                >
                                    <x-heroicon-m-clipboard-document
                                        x-show="copiedIndex !== {{ $index }}"
                                        class="size-4 text-primary-500"
                                        aria-hidden="true"
                                    />
                                    <x-heroicon-m-check
                                        x-show="copiedIndex === {{ $index }}"
                                        x-cloak
                                        class="size-4 text-green-500"
                                        aria-hidden="true"
                                    />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
