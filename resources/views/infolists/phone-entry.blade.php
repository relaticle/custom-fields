@php
    $state = $getState();
    $rawEntries = is_array($state) ? $state : ($state ? [$state] : []);
    $countryOptions = config('custom-fields.phone.country_codes', []);

    // Normalize entries - handle both string format ("+16143752923") and object format ({country, number})
    $entries = collect($rawEntries)->map(function($entry) {
        if (is_string($entry) && !empty($entry)) {
            // String format - just the phone number
            return ['display' => $entry, 'tel' => preg_replace('/[^0-9+]/', '', $entry)];
        } elseif (is_array($entry) && !empty($entry['number'])) {
            // Object format with country and number
            $country = $entry['country'] ?? 'US';
            $countryOptions = config('custom-fields.phone.country_codes', []);
            $countryCode = $countryOptions[$country] ?? '+1';
            preg_match('/(\d+)/', $countryCode, $matches);
            $code = $matches[1] ?? '1';
            $number = preg_replace('/[^0-9]/', '', $entry['number']);
            return [
                'display' => $countryCode . ' ' . $entry['number'],
                'tel' => '+' . $code . $number,
            ];
        }
        return null;
    })->filter()->values()->all();
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
            <div class="group relative inline-flex items-center py-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors max-w-full">
                <a
                    href="tel:{{ $entry['tel'] }}"
                    class="text-primary-600 dark:text-primary-400 underline decoration-gray-300 dark:decoration-gray-600 decoration-1 underline-offset-2 truncate max-w-[250px]"
                    title="{{ $entry['display'] }}"
                >
                    {{ $entry['display'] }}
                </a>
                <button
                    type="button"
                    x-on:click="copyToClipboard(@js($entry['display']), {{ $index }}, $event)"
                    aria-label="Copy {{ $entry['display'] }} to clipboard"
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
        @empty
            <span class="text-gray-400 dark:text-gray-500">—</span>
        @endforelse
    </div>
</x-dynamic-component>
