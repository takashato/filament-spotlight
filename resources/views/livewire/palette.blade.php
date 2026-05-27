{{-- SECURITY: result fields render via {{ }} (escaped). MatchHighlighter output --}}
{{-- is the only pre-escaped HTML and uses {!! !!} deliberately (Phase 6). --}}
{{-- ARIA INVARIANTS (WCAG 2.1 AA combobox pattern): --}}
{{--   - Search input: role=combobox, aria-controls -> #spotlight-listbox --}}
{{--   - Result wrapper: role=listbox, id=spotlight-listbox --}}
{{--   - Live region: role=status aria-live=polite, sr-only --}}
<div
    x-data="spotlight"
    x-on:keydown.window.escape="handleEscape()"
    x-on:spotlight-open.window="open()"
    x-on:spotlight-close.window="close()"
    x-on:spotlight:dispatch.window="dispatch($event.detail.directive)"
    wire:ignore.self
    class="spotlight-root"
>
    <template x-teleport="body">
        <div
            x-show="isOpen"
            x-cloak
            class="spotlight-overlay fixed inset-0 z-[9999] flex items-start justify-center bg-gray-950/40 px-4 pt-[12vh] backdrop-blur-sm"
            x-transition.opacity
            x-on:click.self="close()"
            role="presentation"
        >
            <div
                x-ref="modal"
                x-trap.inert.noscroll="isOpen"
                x-on:keydown.tab="trapTab($event)"
                role="dialog"
                aria-modal="true"
                aria-label="{{ __('spotlight::spotlight.trigger_label') }}"
                class="spotlight-modal w-full max-w-[640px] overflow-hidden rounded-xl bg-white shadow-2xl ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-white/10"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-2"
                x-transition:enter-end="opacity-100 translate-y-0"
            >
                {{-- ARIA live region: announces result counts + open/close transitions to screen readers. --}}
                <div
                    role="status"
                    aria-live="polite"
                    aria-atomic="true"
                    class="sr-only"
                    x-text="resultsAnnouncement"
                ></div>

                <div class="flex items-center gap-3 border-b border-gray-200 px-4 dark:border-white/10">
                    <x-filament::icon
                        icon="heroicon-o-magnifying-glass"
                        class="h-5 w-5 text-gray-400 dark:text-gray-500"
                    />
                    <input
                        x-ref="input"
                        type="search"
                        autocomplete="off"
                        spellcheck="false"
                        wire:model.live.debounce.{{ (int) config('spotlight.debounce_ms', 200) }}ms="query"
                        x-on:keydown="handleListKey($event)"
                        placeholder="{{ __('spotlight::spotlight.search_placeholder') }}"
                        role="combobox"
                        aria-expanded="true"
                        aria-controls="spotlight-listbox"
                        aria-autocomplete="list"
                        aria-label="{{ __('spotlight::spotlight.search_placeholder') }}"
                        :aria-activedescendant="highlightedRowDomId"
                        class="spotlight-input h-11 w-full border-0 bg-transparent text-base text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-0 focus:border-0 dark:text-white dark:placeholder-gray-500"
                    />
                    <kbd class="hidden text-[11px] font-medium text-gray-400 sm:block dark:text-gray-500">
                        ESC
                    </kbd>
                </div>

                {{-- ARIA: role=listbox; option rows live inside <x-spotlight::group>. --}}
                <div
                    id="spotlight-listbox"
                    role="listbox"
                    aria-label="{{ __('spotlight::spotlight.search_placeholder') }}"
                    class="spotlight-results max-h-[60vh] overflow-y-auto"
                    wire:loading.class="opacity-60"
                    wire:target="query"
                >
                    <div wire:loading.delay wire:target="query" class="px-2 py-2" aria-hidden="true">
                        <x-spotlight::loading-skeleton />
                    </div>

                    @php($groups = $this->groups)
                    @php($actionsForFocused = $this->actionsForFocused)

                    @if ($groups->flatten(1)->isEmpty())
                        <x-spotlight::empty-state :query="$query" />
                    @else
                        <div wire:loading.remove wire:target="query">
                            @foreach ($groups as $sourceKey => $results)
                                @if ($results->isNotEmpty())
                                    <x-spotlight::group
                                        :source-key="$sourceKey"
                                        :results="$results"
                                        :highlighted-id="$highlightedId"
                                    />
                                @endif
                            @endforeach
                        </div>
                    @endif

                    {{-- Per-row actions submenu — auto-resolves on highlight change --}}
                    {{-- via $wire.loadActionsForFocused. Tab focuses first action. --}}
                    @if ($highlightedId !== null && ! empty($actionsForFocused))
                        <x-spotlight::action-submenu
                            :actions="$actionsForFocused"
                            :result-id="$highlightedId"
                            :result-title="''"
                        />
                    @endif
                </div>

                <div class="hidden items-center justify-between gap-4 border-t border-gray-200 bg-gray-50 px-4 py-2 text-[11px] text-gray-500 sm:flex dark:border-white/10 dark:bg-gray-950/50 dark:text-gray-400">
                    <div class="flex items-center gap-3">
                        <span><kbd>↑↓</kbd> {{ __('spotlight::spotlight.footer_navigate') }}</span>
                        <span><kbd>↵</kbd> {{ __('spotlight::spotlight.footer_open') }}</span>
                        <span><kbd>⇥</kbd> {{ __('spotlight::spotlight.footer_next_group') }}</span>
                    </div>
                    <span><kbd>esc</kbd> {{ __('spotlight::spotlight.footer_close') }}</span>
                </div>
            </div>
        </div>
    </template>

    {{-- Filament action lifecycle: modals/forms/confirm dialogs render here. --}}
    {{-- Placed outside the teleported overlay so the action modal can stack on --}}
    {{-- top of the palette without fighting its x-trap focus boundary. --}}
    <x-filament-actions::modals />
</div>
