@props([
    'shortcut' => null,
])

<button
    type="button"
    x-data
    x-on:click.prevent="$dispatch('spotlight-open')"
    class="spotlight-trigger inline-flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-1.5 text-sm text-gray-500 shadow-sm transition hover:border-gray-300 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400 dark:hover:border-white/20 dark:hover:text-gray-200"
    aria-keyshortcuts="{{ $shortcut ?? config('spotlight.shortcut.keys', 'mod+k') }}"
    aria-label="{{ __('spotlight::spotlight.trigger_label') }}"
>
    <x-filament::icon icon="heroicon-o-magnifying-glass" class="h-4 w-4" aria-hidden="true" />
    <span class="hidden sm:inline">{{ __('spotlight::spotlight.search_placeholder') }}</span>
    <kbd class="hidden rounded border border-gray-200 bg-gray-50 px-1.5 py-0.5 text-[10px] font-medium uppercase text-gray-500 sm:inline-block dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
        ⌘K
    </kbd>
</button>
