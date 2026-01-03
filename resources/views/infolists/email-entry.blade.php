@php
    $state = $getState();
    $entries = is_array($state) ? $state : ($state ? [$state] : []);
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
                setTimeout(() => {
                    this.copiedIndex = null;
                }, 2000);
            }
        }"
        class="flex flex-wrap gap-x-2 gap-y-1"
    >
        @forelse ($entries as $index => $entry)
            @if (!empty($entry))
                <div class="group inline-flex items-center gap-1 py-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                    <a
                        href="mailto:{{ $entry }}"
                        class="text-primary-600 dark:text-primary-400 underline decoration-gray-300 dark:decoration-gray-600 decoration-1 underline-offset-2"
                    >
                        {{ $entry }}
                    </a>
                    <button
                        type="button"
                        x-on:click="copyToClipboard(@js($entry), {{ $index }}, $event)"
                        class="opacity-0 group-hover:opacity-100 transition-opacity shrink-0 p-0.5 rounded hover:bg-gray-200 dark:hover:bg-gray-700"
                    >
                        <x-heroicon-m-clipboard-document
                            x-show="copiedIndex !== {{ $index }}"
                            class="size-4 text-gray-400"
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
            @endif
        @empty
            <span class="text-gray-400 dark:text-gray-500">—</span>
        @endforelse
    </div>
</x-dynamic-component>
