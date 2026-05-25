<?php

declare(strict_types=1);

/**
 * Asserts the WCAG 2.1 AA combobox ARIA contract on the spotlight palette.
 *
 * The full Filament view stack (icon manifest, Livewire validation bootstrap)
 * is verified by the Phase 7 host smoke test — testbench cannot bootstrap it
 * standalone. Here we assert the static ARIA invariants directly against the
 * Blade source so the contract cannot silently regress.
 */
function spotlightView(string $relative): string
{
    $path = __DIR__.'/../../resources/views/'.$relative;

    return (string) file_get_contents($path);
}

it('declares the combobox role and listbox controls on the search input', function (): void {
    $view = spotlightView('livewire/palette.blade.php');

    expect($view)->toContain('role="combobox"');
    expect($view)->toContain('aria-controls="spotlight-listbox"');
    expect($view)->toContain('aria-autocomplete="list"');
    expect($view)->toContain(':aria-activedescendant="highlightedRowDomId"');
});

it('declares the listbox container with a stable id', function (): void {
    $view = spotlightView('livewire/palette.blade.php');

    expect($view)->toContain('id="spotlight-listbox"');
    expect($view)->toContain('role="listbox"');
});

it('exposes an aria-live polite status region', function (): void {
    $view = spotlightView('livewire/palette.blade.php');

    expect($view)->toContain('aria-live="polite"');
    expect($view)->toContain('role="status"');
    expect($view)->toContain('class="sr-only"');
});

it('marks the modal as a dialog', function (): void {
    $view = spotlightView('livewire/palette.blade.php');

    expect($view)->toContain('role="dialog"');
    expect($view)->toContain('aria-modal="true"');
});

it('renders option rows with role=option, stable ids and aria-selected', function (): void {
    $view = spotlightView('components/result.blade.php');

    expect($view)->toContain('role="option"');
    expect($view)->toContain('id="{{ $rowId }}"');
    // aria-selected is reactive (Alpine binding) so the active row tracks
    // keyboard navigation without a server round-trip.
    expect($view)->toContain(':aria-selected="highlightedId === @js($rowKey) ? \'true\' : \'false\'"');
    // Stable id template prefix lives in @php block.
    expect($view)->toContain("'spotlight-result-'.\$result->sourceKey()");
});

it('declares group landmarks with localized aria-label', function (): void {
    $view = spotlightView('components/group.blade.php');

    expect($view)->toContain('role="group"');
    expect($view)->toContain('aria-label="{{ $groupLabel }}"');
});

it('declares aria-keyshortcuts and a localized label on the trigger pill', function (): void {
    $view = spotlightView('components/trigger-pill.blade.php');

    expect($view)->toContain('aria-keyshortcuts="{{ $shortcut ??');
    expect($view)->toContain("__('spotlight::spotlight.trigger_label')");
});

it('escapes the result subtitle and uses raw HTML only for the title', function (): void {
    $view = spotlightView('components/result.blade.php');

    // Subtitle must remain escaped via {{ }}.
    expect($view)->toContain('{{ $result->subtitle() }}');
    // Title is the only field rendered as raw HTML (pre-escaped by MatchHighlighter).
    expect($view)->toContain('{!! $result->title() !!}');
});

it('honours prefers-reduced-motion for the modal animations', function (): void {
    $css = (string) file_get_contents(__DIR__.'/../../resources/css/spotlight.css');

    expect($css)->toContain('prefers-reduced-motion: reduce');
    expect($css)->toContain('animation: none !important');
});

it('flips directional icons for RTL layouts', function (): void {
    $css = (string) file_get_contents(__DIR__.'/../../resources/css/spotlight.css');

    expect($css)->toContain('[dir="rtl"]');
    expect($css)->toContain('rotate(180deg)');
});

it('forces 16px font on the input to prevent iOS Safari zoom', function (): void {
    $css = (string) file_get_contents(__DIR__.'/../../resources/css/spotlight.css');

    expect($css)->toContain('.spotlight-input');
    expect($css)->toContain('font-size: 16px');
});

it('uses dynamic viewport height with a fallback for the mobile sheet', function (): void {
    $css = (string) file_get_contents(__DIR__.'/../../resources/css/spotlight.css');

    expect($css)->toContain('height: 100vh');
    expect($css)->toContain('height: 100dvh');
});
