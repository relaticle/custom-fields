@php use Illuminate\Support\HtmlString; @endphp
<div
        id="{{ $field->id }}"
        class="fi-section !px-2 fi-compact !py-2 shadow-none fi-grid-col flex justify-between"
        style="--col-span-default: span {{ $field->width->getSpanValue() }} / span 12;"
        x-sortable-item="{{ $field->id }}"
        compact
>
    <div class="flex items-center gap-x-2 w-full truncate" x-sortable-handle>
        <x-filament::icon-button
                icon="heroicon-m-bars-3"
                color="gray"
        />

        <x-filament::icon
                :icon="$field->typeData?->icon ?? 'heroicon-o-document-text'"
                class="h-5 w-5 text-gray-500 dark:text-gray-400"
                :aria-label="$field->name"
        />

        {{
            $this->editAction()->icon(false)
                            ->label(new HtmlString('<span class="truncate flex">'.$field->name.'</span>'))
                            ->extraAttributes(['class' => 'truncate', 'x-tooltip.raw' => $field->name])
                            ->link()
        }}

        @if(!$field->isActive())
            <x-filament::badge color="warning" size="sm">
                {{ __('custom-fields::custom-fields.common.inactive') }}
            </x-filament::badge>
        @endif
    </div>

    <div class="flex items-center gap-x-1 py-0.5">

        <livewire:manage-custom-field-width
                :selected-width="$field->width"
                :field-id="$field->id"
                wire:key="manage-custom-field-width-{{ $field->id }}"
        />

        {{ $this->actions() }}
    </div>
    <x-filament-actions::modals/>
</div>

