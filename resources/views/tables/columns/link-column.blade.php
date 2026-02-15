@php
    $state = $getState();
    $entries = is_array($state) ? array_values(array_filter($state, fn($e) => !empty($e))) : [];
    $maxVisible = 1;
    $visibleEntries = array_slice($entries, 0, $maxVisible);
    $hiddenCount = max(0, count($entries) - $maxVisible);

    /**
     * Get the full URL with protocol for href
     */
    $getFullUrl = function (string $url): string {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        return 'https://' . $url;
    };

    /**
     * Get a display-friendly version of the URL (without protocol)
     */
    $getDisplayUrl = function (string $url): string {
        return preg_replace('#^https?://#', '', $url);
    };
@endphp

<div
    x-data="{
        copiedIndex: null,

        isOpen() {
            return this.$refs.panel?._x_isShown === true;
        },

        togglePanel() {
            this.$refs.panel?.toggle(this.$refs.trigger);
        },

        closePanel() {
            this.$refs.panel?.close();
        },

        copyToClipboard(text, index, event) {
            event.preventDefault();
            event.stopPropagation();
            window.navigator.clipboard.writeText(text);
            this.copiedIndex = index;
            this.announceToScreenReader('Copied ' + text + ' to clipboard');
            setTimeout(() => {
                this.copiedIndex = null;
            }, 2000);
        },

        announceToScreenReader(message) {
            if (this.$refs.announcer) {
                this.$refs.announcer.textContent = message;
            }
        }
    }"
    x-on:keydown.esc="isOpen() && (closePanel(), $event.stopPropagation())"
    x-on:click.outside="closePanel()"
    @if ($hiddenCount > 0)
        x-ref="trigger"
        :aria-expanded="isOpen() ? 'true' : 'false'"
        aria-haspopup="menu"
        :aria-controls="$id('panel')"
        x-on:click="togglePanel()"
        x-on:keydown.enter.prevent="togglePanel()"
        x-on:keydown.space.prevent="togglePanel()"
    @endif
    class="fi-ta-text relative {{ $hiddenCount > 0 ? 'cursor-pointer' : '' }}"
>
    <div x-ref="announcer" aria-live="polite" aria-atomic="true" class="sr-only"></div>

    @if (filled($entries))
        <div class="inline-flex items-center gap-x-3 text-left">
            @foreach ($visibleEntries as $index => $entry)
                <div class="group/value relative inline-flex items-center py-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors shrink-0">
                    <a
                        href="{{ $getFullUrl($entry) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        x-on:click.stop
                        class="text-sm text-primary-600 dark:text-primary-400 underline decoration-gray-300 dark:decoration-gray-600 decoration-1 underline-offset-2 truncate max-w-[200px]"
                        title="{{ $entry }}"
                    >{{ $getDisplayUrl($entry) }}</a>
                    <button
                            type="button"
                            x-on:click.stop="copyToClipboard(@js($getFullUrl($entry)), 'visible-{{ $index }}', $event)"
                            aria-label="Copy {{ $entry }} to clipboard"
                            class="absolute right-0 opacity-0 group-hover/value:opacity-100 focus:opacity-100 transition-opacity duration-300 py-0.5 pl-2 pr-1 rounded-r bg-gradient-to-r from-gray-100/90 via-gray-100/100 to-gray-100 dark:from-gray-700/0 dark:via-gray-700/70 dark:to-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
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
                <span class="text-sm text-gray-400 dark:text-gray-500 whitespace-nowrap" aria-hidden="true">+{{ $hiddenCount }}</span>
                <span class="sr-only">and {{ $hiddenCount }} more. Press Enter to view all.</span>
            @endif
        </div>

        {{-- Expanded Popover - All values --}}
        @if ($hiddenCount > 0)
            <div
                    x-cloak
                    x-float.placement.bottom-start.flip.offset.teleport="{ offset: 4 }"
                    x-transition:enter-start="opacity-0"
                    x-transition:leave-end="opacity-0"
                    x-ref="panel"
                    role="menu"
                    :id="$id('panel')"
                    aria-label="All links"
                    class="absolute z-50 w-[220px] rounded-lg bg-white shadow-lg ring-1 ring-gray-950/5 transition dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="py-1 max-h-[280px] overflow-y-auto overflow-x-auto">
                    @foreach ($entries as $index => $entry)
                        <div
                                role="menuitem"
                                class="flex items-center px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors"
                        >
                            <div class="group/value relative inline-flex items-center py-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <a
                                        href="{{ $getFullUrl($entry) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        x-on:click.stop
                                        class="text-sm text-primary-600 dark:text-primary-400 underline decoration-gray-300 dark:decoration-gray-600 decoration-1 underline-offset-2 truncate max-w-[200px]"
                                        title="{{ $entry }}"
                                >{{ $getDisplayUrl($entry) }}</a>
                                <button
                                        type="button"
                                        x-on:click.stop="copyToClipboard(@js($getFullUrl($entry)), {{ $index }}, $event)"
                                        aria-label="Copy {{ $entry }} to clipboard"
                                        class="absolute right-0 opacity-0 group-hover/value:opacity-100 focus:opacity-100 transition-opacity duration-300 py-0.5 pl-2 pr-1 rounded-r bg-gradient-to-r from-gray-100/90 via-gray-100/100 to-gray-100 dark:from-gray-700/0 dark:via-gray-700/70 dark:to-gray-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
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
