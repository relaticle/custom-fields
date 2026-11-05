@php
    $chipTag = filled($chip['url']) ? 'a' : 'span';
@endphp

<{{ $chipTag }}
    @if (filled($chip['url'])) href="{{ $chip['url'] }}" x-on:click.stop @endif
    @if (filled($chip['provenance']))
        title="{{ $chip['provenance'] }}"
        data-provenance="{{ $chip['provenance'] }}"
    @endif
    class="fi-cf-record-chip inline-flex max-w-full items-center gap-1.5 rounded-md bg-gray-50 py-1 ps-1 pe-2 text-sm text-gray-950 ring-1 ring-gray-950/5 transition hover:bg-gray-100 dark:bg-white/5 dark:text-white dark:ring-white/10 dark:hover:bg-white/10"
>
    @if (filled($chip['avatarUrl']))
        <img
            src="{{ $chip['avatarUrl'] }}"
            alt=""
            class="h-5 w-5 shrink-0 object-cover {{ $chip['avatarShape'] }}"
        />
    @else
        <span
            aria-hidden="true"
            class="flex h-5 w-5 shrink-0 items-center justify-center bg-gray-200 text-[0.625rem] font-semibold uppercase text-gray-600 dark:bg-white/10 dark:text-gray-300 {{ $chip['avatarShape'] }}"
        >{{ mb_substr($chip['name'], 0, 1) }}</span>
    @endif

    <span class="truncate">{{ $chip['name'] }}</span>

    @if (filled($chip['provenance']))
        <span class="sr-only">{{ $chip['provenance'] }}</span>
    @endif
</{{ $chipTag }}>
