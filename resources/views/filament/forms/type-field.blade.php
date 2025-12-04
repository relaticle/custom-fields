<div class="flex items-center gap-1 relative">
    <x-filament::icon
            :icon="$icon"
            class="h-4 w-4 text-gray-400 dark:text-gray-500 absolute"
            :aria-label="$label"
    />
    <span style="margin-inline-start: 1.2rem">{{ $label }}</span>
</div>
