<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Concerns;

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;

/**
 * Registers Filament render hooks that mount the trigger pill and Livewire palette.
 *
 * Hooks are gated to authenticated panel sessions only — login + register pages
 * intentionally skip mounting. The body-end teleport target is rendered once.
 */
trait RegistersRenderHooks
{
    protected function registerRenderHooks(): void
    {
        /** @var view-string $triggerView */
        $triggerView = 'spotlight::components.trigger-pill';

        // Render before the global-search slot so the trigger pill sits ahead
        // of notifications, user menu, and other topbar widgets.
        FilamentView::registerRenderHook(
            PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            fn (): string => $this->shouldMountSpotlight()
                ? view($triggerView)->render()
                : '',
        );

        // Wrap in @persist so the Livewire component survives wire:navigate
        // morphs (Filament's `->spa()` mode) — no remount, no flicker.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => $this->shouldMountSpotlight()
                ? Blade::render("@persist('spotlight-palette')<livewire:spotlight-palette />@endpersist")
                : '',
        );
    }

    protected function shouldMountSpotlight(): bool
    {
        if (! Filament::isServing()) {
            return false;
        }

        // Skip on auth pages — no panel context, no auth user.
        return Auth::guard(Filament::getAuthGuard())->check();
    }
}
