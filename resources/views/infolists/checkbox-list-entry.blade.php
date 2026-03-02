@php
    $state = $getState();
    $options = $state['options'] ?? [];
    $selected = $state['selected'] ?? [];
    $optionColors = $state['optionColors'] ?? [];
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if (empty($options))
        <span class="text-gray-400 dark:text-gray-500">&mdash;</span>
    @else
        <div class="flex flex-col gap-2">
            @foreach ($options as $value => $label)
                @php
                    $isChecked = in_array($value, $selected, false);
                    $color = $optionColors[$value] ?? null;
                @endphp
                <label class="flex items-center gap-x-3">
                    <input
                        type="checkbox"
                        disabled
                        @checked($isChecked)
                        @if ($color)
                            style="--c-400: {{ $color }}; --c-600: {{ $color }};"
                        @endif
                        class="fi-checkbox-input rounded border-gray-300 text-primary-600 shadow-sm focus:ring-0 disabled:opacity-70 dark:border-gray-600 dark:bg-gray-800"
                    />
                    <span class="text-sm text-gray-950 dark:text-white">{{ $label }}</span>
                </label>
            @endforeach
        </div>
    @endif
</x-dynamic-component>
