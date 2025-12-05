<div
    x-data="{
        fieldId: @entangle('fieldId'),
        selectedWidth: @entangle('selectedWidth'),
        widths: @js($widthOptions),
        isSelected(width) {
            return width <= this.selectedWidth;
        }
    }"
    class="relative"
>
    <div class="w-20 flex relative h-6">
        <div class="absolute w-20 flex opacity-70" style="z-index: 1">
            <template x-for="(width, index) in widths" :key="index">
                <div
                    wire:click="$parent.setWidth(fieldId, width)"
                    class="h-6 flex-1 cursor-pointer bg-gray-200 hover:bg-gray-300 transition-colors"
                    :class="{
                    'rounded-s-md': index === 0,
                    'rounded-e-md': index === widths.length - 1
                }"
                >
                    <div
                        class="h-full w-full border-gray-300 transition-colors duration-200"
                        :class="{
                        'bg-primary-600 hover:bg-primary-600/80': isSelected(width),
                        'rounded-s-md': index === 0 && isSelected(width),
                        'rounded-e-md': index === widths.length - 1 && isSelected(width),
                        'border-s': index !== widths.length - 1
                    }"
                    ></div>
                </div>
            </template>
        </div>
        <div class="absolute w-full h-full font-semibold text-sm flex items-center justify-center text-black">{{ $selectedWidth }}%</div>
    </div>
</div>
