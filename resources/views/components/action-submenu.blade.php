@props([
    'actions' => [],
    'resultId' => null,
    'resultTitle' => '',
    'titleId' => null,
])

@php
    $hasActions = ! empty($actions);
    $count = is_countable($actions) ? count($actions) : 0;
@endphp

<div
    x-on:keydown.escape.stop.prevent="returnFocusToInput()"
    x-on:keydown.tab.prevent="returnFocusToInput()"
    x-on:keydown.arrow-down.prevent="moveSubmenu(1)"
    x-on:keydown.arrow-up.prevent="moveSubmenu(-1)"
    role="menu"
    @if ($titleId) aria-labelledby="{{ $titleId }}" @endif
    aria-label="{{ __('spotlight::spotlight.actions.label') }}"
    class="spotlight-submenu border-l-2 border-primary-400/40 bg-gray-50/80 px-3 py-2 dark:border-primary-400/30 dark:bg-white/5"
    data-spotlight-submenu
>
    <template x-if="submenuLoading">
        <div class="px-2 py-2" aria-hidden="true">
            <x-spotlight::loading-skeleton />
        </div>
    </template>

    @if (! $hasActions)
        <p
            x-show="! submenuLoading"
            class="px-2 py-1.5 text-xs text-gray-500 dark:text-gray-400"
            role="none"
        >
            {{ __('spotlight::spotlight.actions.empty') }}
        </p>
    @else
        <ul
            x-show="! submenuLoading"
            role="none"
            class="flex flex-col gap-1"
            data-spotlight-submenu-items
        >
            @foreach ($actions as $action)
                <li role="none" class="spotlight-submenu-item">
                    {{ $action }}
                </li>
            @endforeach
        </ul>
    @endif

    <span class="sr-only" aria-live="polite">
        {{ __('spotlight::spotlight.actions.announce', ['count' => $count, 'title' => $resultTitle]) }}
    </span>
</div>
