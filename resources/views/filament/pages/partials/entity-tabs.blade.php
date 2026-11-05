@foreach ($this->entityTypes as $key => $label)
    @php
        $entity = \Relaticle\CustomFields\Facades\Entities::getEntity($key);
    @endphp
    <x-filament::tabs.item
        :icon="$entity?->getIcon() ?? 'heroicon-o-document'"
        :active="$key === $this->currentEntityType"
        :badge="$this->entityFieldCounts[$key] ?? 0"
        wire:click="setCurrentEntityType('{{ addslashes($key) }}')"
    >
        {{ $label }}
    </x-filament::tabs.item>
@endforeach
