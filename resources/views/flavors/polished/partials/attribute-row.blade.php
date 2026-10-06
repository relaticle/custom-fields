@php
    $isActive = $field->isActive();
    $isSystemDefined = $field->isSystemDefined();

    $pairSentence = $pair === null ? null : match (true) {
        $pair['symmetric'] => __('custom-fields::custom-fields.field.form.pair.symmetric', ['entity' => $pair['entity']]),
        $pair['partner_name'] !== null => __('custom-fields::custom-fields.field.form.pair.paired', ['field' => $pair['partner_name'], 'entity' => $pair['entity']]),
        default => __('custom-fields::custom-fields.field.form.pair.one_way', ['entity' => $pair['entity']]),
    };
@endphp

<div
    @if ($sortable) x-sortable-item="{{ $field->getKey() }}" @endif
    wire:key="{{ $sortable ? 'field' : 'inactive-field' }}-{{ $field->getKey() }}"
    data-field="{{ $field->getKey() }}"
    @if ($pair !== null)
        data-pair="{{ $pair['definition'] }}"
        @if ($pair['partner_id'] !== null) data-pair-partner="{{ $pair['partner_id'] }}" @endif
    @endif
    class="fi-cf-attribute-row grid min-h-[3.25rem] grid-cols-[40px_minmax(0,1fr)_minmax(120px,180px)_minmax(120px,1fr)_50px] items-center transition duration-75 hover:bg-gray-50 dark:hover:bg-white/5 @if ($pair !== null && $pair['partner_id'] !== null) border-s-2 border-s-primary-500 dark:border-s-primary-400 @endif"
>
    <div class="flex items-center justify-center py-3">
        @if ($sortable)
            <div
                x-sortable-handle
                class="cursor-grab text-gray-400 transition-colors hover:text-gray-600 active:cursor-grabbing dark:hover:text-gray-300"
                aria-label="{{ __('custom-fields::custom-fields.common.reorder') }}"
            >
                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <circle cx="7" cy="5" r="1.5"/>
                    <circle cx="13" cy="5" r="1.5"/>
                    <circle cx="7" cy="10" r="1.5"/>
                    <circle cx="13" cy="10" r="1.5"/>
                    <circle cx="7" cy="15" r="1.5"/>
                    <circle cx="13" cy="15" r="1.5"/>
                </svg>
            </div>
        @endif
    </div>

    <div class="flex min-w-0 items-center gap-2 px-3 py-3 @unless ($isActive) opacity-60 @endunless">
        @if ($field->typeData?->icon)
            <x-filament::icon :icon="$field->typeData->icon" class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500"/>
        @endif
        <span class="truncate text-sm text-gray-950 dark:text-white">{{ $field->name }}</span>
    </div>

    <div class="px-3 py-3 @unless ($isActive) opacity-60 @endunless">
        <span class="truncate text-sm text-gray-600 dark:text-gray-400">{{ $field->typeData?->label }}</span>

        @if ($pairSentence !== null)
            {{-- The column is too narrow for the sentence, and which entity the field pairs to
                 is the whole content, so it wraps instead of being cut. --}}
            <span
                class="mt-0.5 flex items-start gap-1 text-xs text-primary-600 dark:text-primary-400"
                title="{{ $pairSentence }}"
            >
                <x-filament::icon icon="heroicon-m-arrows-right-left" class="mt-0.5 h-3 w-3 shrink-0" aria-hidden="true"/>
                <span>{{ $pairSentence }}</span>
            </span>
        @endif
    </div>

    <div class="flex flex-wrap items-center gap-1 px-3 py-3 @unless ($isActive) opacity-60 @endunless">
        @if ($isSystemDefined)
            <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                <x-filament::icon icon="heroicon-o-cog-6-tooth" class="h-3 w-3 shrink-0"/>
                {{ __('custom-fields::custom-fields.common.system') }}
            </span>
        @endif

        @if ($field->settings?->unique_per_entity_type)
            <span class="inline-flex items-center whitespace-nowrap rounded-md bg-info-50 px-2 py-1 text-xs font-medium text-info-700 dark:bg-info-400/10 dark:text-info-300">
                {{ __('custom-fields::custom-fields.common.unique') }}
            </span>
        @endif

        @if ($field->validation_rules?->has('required'))
            <span class="inline-flex items-center whitespace-nowrap rounded-md bg-danger-50 px-2 py-1 text-xs font-medium text-danger-700 dark:bg-danger-400/10 dark:text-danger-300">
                {{ __('custom-fields::custom-fields.common.required') }}
            </span>
        @endif

        @unless ($isActive)
            <span class="inline-flex items-center whitespace-nowrap rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300">
                {{ __('custom-fields::custom-fields.common.archived') }}
            </span>
        @endunless
    </div>

    <div class="flex items-center justify-center gap-1 py-3">
        @unless ($isActive)
            {{ ($this->activateFieldAction)(['fieldId' => $field->getKey()]) }}
        @endunless

        <x-filament-actions::group
            :actions="array_filter([
                ($this->editFieldAction)(['fieldId' => $field->getKey()]),
                $isActive && ! $isSystemDefined
                    ? ($this->deactivateFieldAction)(['fieldId' => $field->getKey()])
                    : null,
                ! $isActive && ! $isSystemDefined
                    ? ($this->deleteFieldAction)(['fieldId' => $field->getKey()])
                    : null,
            ])"
            dropdown-placement="bottom-end"
        />
    </div>
</div>
