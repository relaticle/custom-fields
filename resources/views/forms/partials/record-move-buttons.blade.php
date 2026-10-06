{{-- The links are written in the order the chips are left in, so that order needs an
     affordance a keyboard can reach. Shared by both flavors, like the state object. --}}
<template x-if="allowMultiple && selectedRecords.length > 1">
    <span class="fi-cf-record-chip-move inline-flex items-center">
        <button
            type="button"
            x-on:click.stop="moveRecord(record.id, -1)"
            x-on:keydown.enter.stop.prevent="moveRecord(record.id, -1)"
            x-on:keydown.space.stop.prevent="moveRecord(record.id, -1)"
            :disabled="isDisabled || index === 0"
            :aria-label="@js(__('custom-fields::custom-fields.record.move_up', ['record' => ':record'])).replace(':record', record.label)"
            class="rounded p-0.5 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:pointer-events-none disabled:opacity-40 dark:hover:text-gray-200"
        >
            <x-filament::icon icon="heroicon-m-chevron-up" class="size-3" />
        </button>

        <button
            type="button"
            x-on:click.stop="moveRecord(record.id, 1)"
            x-on:keydown.enter.stop.prevent="moveRecord(record.id, 1)"
            x-on:keydown.space.stop.prevent="moveRecord(record.id, 1)"
            :disabled="isDisabled || index === selectedRecords.length - 1"
            :aria-label="@js(__('custom-fields::custom-fields.record.move_down', ['record' => ':record'])).replace(':record', record.label)"
            class="rounded p-0.5 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:pointer-events-none disabled:opacity-40 dark:hover:text-gray-200"
        >
            <x-filament::icon icon="heroicon-m-chevron-down" class="size-3" />
        </button>
    </span>
</template>
