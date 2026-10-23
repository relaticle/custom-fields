@php
    use Illuminate\Support\Str;

    $fields = $getConfiguredFields();
    $sourceEntity = $getSourceEntity();
    $targetEntity = $getTargetEntity();
    $sentence = $getCardinalitySentence();
    $isSymmetric = $isSymmetric();
    $pairsAField = $pairsAField();
@endphp

<div
    data-flavor="polished"
    data-surface="relationship-configurator"
    {{
        $attributes
            ->merge(['id' => $getId()], escape: false)
            ->merge($getExtraAttributes(), escape: false)
            ->class(['fi-sc-cf-relationship-configurator flex flex-col gap-4'])
    }}
>
    <div
        class="flex items-start gap-3 rounded-xl bg-primary-50 p-4 text-sm text-primary-700 ring-1 ring-primary-600/10 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/20"
        role="note"
    >
        <x-filament::icon
            icon="heroicon-o-arrows-right-left"
            class="mt-0.5 h-5 w-5 shrink-0"
            aria-hidden="true"
        />
        <p>
            {{ $isSymmetric
                ? __('custom-fields::custom-fields.field.form.record.sync_banner_symmetric')
                : ($pairsAField
                    ? __('custom-fields::custom-fields.field.form.record.sync_banner')
                    : __('custom-fields::custom-fields.field.form.record.sync_banner_one_way')) }}
        </p>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,14rem)_minmax(0,1fr)] md:items-start">
        <section
            class="fi-sc-cf-relationship-end rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
            aria-label="{{ __('custom-fields::custom-fields.field.form.record.this_entity') }}"
        >
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                {{ __('custom-fields::custom-fields.field.form.record.this_entity') }}
            </p>

            <div class="mt-2 flex items-center gap-2">
                <x-filament::icon
                    :icon="$sourceEntity?->getIcon() ?? 'heroicon-o-rectangle-stack'"
                    class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500"
                    aria-hidden="true"
                />
                {{-- A host resource label is written for Filament's sentence use ("Create
                     opportunity"), and this is a heading. --}}
                <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $sourceEntity === null
                        ? __('custom-fields::custom-fields.field.form.record.unknown_entity')
                        : Str::ucfirst($sourceEntity->getLabelSingular()) }}
                </span>
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                {{ __('custom-fields::custom-fields.field.form.record.field_on_this_entity') }}
            </p>
            <p
                class="mt-1 truncate text-sm text-gray-950 dark:text-white"
                x-data="{ fallback: @js($getFieldName()) }"
                x-text="$wire.$get(@js($getFieldNameStatePath())) || fallback"
            >{{ $getFieldName() }}</p>
        </section>

        <div class="fi-sc-cf-relationship-cardinality flex flex-col gap-2">
            @if ($cardinality = ($fields['relationship.cardinality'] ?? null))
                {{ $cardinality }}
            @endif

            <p class="text-sm text-gray-500 dark:text-gray-400" data-cardinality-sentence>
                {{ $sentence ?? __('custom-fields::custom-fields.field.form.record.sentence_placeholder') }}
            </p>
        </div>

        <section
            class="fi-sc-cf-relationship-end flex flex-col gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
            aria-label="{{ __('custom-fields::custom-fields.field.form.record.related_entity') }}"
        >
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    {{ __('custom-fields::custom-fields.field.form.record.related_entity') }}
                </p>

                @if ($targetEntity !== null)
                    <div class="mt-2 flex items-center gap-2">
                        <x-filament::icon
                            :icon="$targetEntity->getIcon()"
                            class="h-5 w-5 shrink-0 text-gray-400 dark:text-gray-500"
                            aria-hidden="true"
                        />
                        <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">
                            {{ Str::ucfirst($targetEntity->getLabelSingular()) }}
                        </span>
                    </div>
                @endif
            </div>

            @foreach (['relationship.target_entity_type', 'relationship.paired_field_name', 'relationship.paired_section_id'] as $name)
                @if ($field = ($fields[$name] ?? null))
                    {{ $field }}
                @endif
            @endforeach
        </section>
    </div>

    @if ($symmetric = ($fields['relationship.is_symmetric'] ?? null))
        <div class="rounded-xl bg-gray-50 p-4 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
            {{ $symmetric }}
        </div>
    @endif

    @if ($keepFirst = ($fields['relationship.keep_first'] ?? null))
        <div class="rounded-xl bg-warning-50 p-4 ring-1 ring-warning-600/20 dark:bg-warning-400/10 dark:ring-warning-400/20">
            {{ $keepFirst }}
        </div>
    @endif
</div>
