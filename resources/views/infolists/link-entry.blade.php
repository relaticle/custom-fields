@php
    $state = $getState();
    $entries = is_array($state) ? $state : ($state ? [$state] : []);

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

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div
        x-data="{
            copiedIndex: null,
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
        class="flex flex-wrap gap-x-3 gap-y-1"
    >
        {{-- Screen reader live region --}}
        <div x-ref="announcer" aria-live="polite" aria-atomic="true" class="sr-only"></div>

        @forelse ($entries as $index => $entry)
            @if (!empty($entry))
                <div class="group relative inline-flex items-center py-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors max-w-full">
                    <a
                        href="{{ $getFullUrl($entry) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-primary-600 dark:text-primary-400 underline decoration-gray-300 dark:decoration-gray-600 decoration-1 underline-offset-2 truncate max-w-[250px]"
                        title="{{ $entry }}"
                    >{{ $getDisplayUrl($entry) }}</a>
                    <button
                        type="button"
                        x-on:click="copyToClipboard(@js($getFullUrl($entry)), {{ $index }}, $event)"
                        aria-label="Copy {{ $entry }} to clipboard"
                        class="absolute right-0 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity duration-300 py-0.5 pl-2 pr-1 rounded-r bg-gradient-to-r from-gray-100/90 via-gray-100/100 to-gray-100 dark:from-gray-800/0 dark:via-gray-800/70 dark:to-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
                    >
                        <x-heroicon-m-clipboard-document
                            x-show="copiedIndex !== {{ $index }}"
                            class="size-3.5 text-primary-500"
                            aria-hidden="true"
                        />
                        <x-heroicon-m-check
                            x-show="copiedIndex === {{ $index }}"
                            x-cloak
                            class="size-3.5 text-green-500"
                            aria-hidden="true"
                        />
                    </button>
                </div>
            @endif
        @empty
            <span class="text-gray-400 dark:text-gray-500">—</span>
        @endforelse
    </div>
</x-dynamic-component>
