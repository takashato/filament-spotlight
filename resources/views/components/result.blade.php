@props([
    'result' => null,
    'isHighlighted' => false,
])

@php
    if (! $result) { return; }
    $directive = $result->handler();
    $payload = method_exists($result, 'payload') ? $result->payload() : [];
    $rowKey = $result->sourceKey().'::'.$result->id();
    $rowId = 'spotlight-result-'.$result->sourceKey().'-'.$result->id();
@endphp

<li
    id="{{ $rowId }}"
    role="option"
    data-spotlight-row="{{ $rowKey }}"
    :aria-selected="highlightedId === @js($rowKey) ? 'true' : 'false'"
    class="spotlight-result group flex cursor-pointer items-center gap-3 px-4 py-2 text-sm transition"
    :class="highlightedId === @js($rowKey)
        ? 'spotlight-result-active bg-primary-50 text-primary-900 dark:bg-primary-500/15 dark:text-white'
        : 'text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5'"
    x-on:click="dispatchResult(@js([
        'sourceKey' => $result->sourceKey(),
        'resultId'  => $result->id(),
        'title'     => $result->title(),
        'directive' => $directive,
        'payload'   => $payload,
    ]))"
    x-on:mouseenter="highlightedId = @js($rowKey)"
>
    @if ($result->icon())
        <x-filament::icon :icon="$result->icon()" class="h-4 w-4 flex-none text-gray-400 group-hover:text-gray-500 dark:text-gray-500" />
    @else
        <span class="h-4 w-4 flex-none"></span>
    @endif

    <div class="flex min-w-0 flex-1 items-center gap-2">
        <span class="truncate">
            {{-- ARIA INVARIANT: title is the ONLY field rendered as raw HTML. --}}
            {{-- MatchHighlighter (Phase 3) e()-escapes the source title and wraps --}}
            {{-- matched substrings with <mark>. Anything that bypasses the highlighter --}}
            {{-- and lands here unescaped is a P0 XSS regression. --}}
            {!! $result->title() !!}
        </span>
        @if ($result->subtitle())
            {{-- Subtitle is plain text — always escape via {{ }}. --}}
            <span class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $result->subtitle() }}</span>
        @endif
    </div>

    @if ($result->badge())
        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-600 dark:bg-white/5 dark:text-gray-400">
            {{ $result->badge() }}
        </span>
    @endif
</li>
