/**
 * Spotlight palette Alpine component.
 *
 * Owns: keyboard navigation, focus management, body-scroll lock, ARIA live
 * announcements, focus trap, and the `spotlight:dispatch` browser-event consumer
 * that turns server-validated directives into actual navigation / event emission.
 *
 * Phase 6 hardening:
 *   - Parses `mod+shift+k` style bindings (mirrors PHP KeyBindingParser)
 *   - Cycles Tab focus inside the modal (no library)
 *   - Recomputes results announcement reactively from data-spotlight-row count
 */
const SHORTCUT_INPUT_TARGETS = ['INPUT', 'TEXTAREA'];
const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

const MOD_TOKENS = ['mod', 'cmd', 'meta', 'ctrl', 'control'];
const ALT_TOKENS = ['alt', 'option', 'opt'];

function parseShortcut(raw) {
    const value = (typeof raw === 'string' ? raw : 'mod+k').toLowerCase().trim();
    if (!value) return defaultBinding();

    const parts = value.split('+').map((p) => p.trim()).filter(Boolean);
    if (!parts.length) return defaultBinding();

    const key = parts.pop();
    if (!key || MOD_TOKENS.includes(key) || ALT_TOKENS.includes(key) || key === 'shift') {
        return defaultBinding();
    }

    return {
        mod: parts.some((p) => MOD_TOKENS.includes(p)),
        shift: parts.includes('shift'),
        alt: parts.some((p) => ALT_TOKENS.includes(p)),
        key,
    };
}

function defaultBinding() {
    return { mod: true, shift: false, alt: false, key: 'k' };
}

function matchesShortcut(event, binding) {
    const modPressed = event.metaKey || event.ctrlKey;
    if (binding.mod && !modPressed) return false;
    if (!binding.mod && modPressed) return false;
    if (binding.shift !== event.shiftKey) return false;
    if (binding.alt !== event.altKey) return false;
    return event.key.toLowerCase() === binding.key;
}

function spotlight() {
    return {
        isOpen: false,
        highlightedId: null,
        previouslyFocused: null,
        binding: parseShortcut(window.spotlightConfig?.shortcut?.keys ?? 'mod+k'),
        resultsAnnouncement: '',
        openSubmenuFor: null,
        submenuLoading: false,
        _morphHandler: null,

        get highlightedRowDomId() {
            if (!this.highlightedId) return null;
            return 'spotlight-result-' + this.highlightedId.replace('::', '-');
        },

        init() {
            window.addEventListener('keydown', (event) => this.handleGlobalKey(event));

            // Recompute live-region announcement + auto-highlight the first row
            // whenever Livewire morphs the result list. Keyboard users always see
            // an active target without needing to press ArrowDown first.
            this._morphHandler = () => {
                this.recomputeAnnouncement();
                this.ensureHighlight();
            };
            document.addEventListener('livewire:morphed', this._morphHandler);
            document.addEventListener('livewire:update', this._morphHandler);

            // Auto-load per-row actions whenever the highlight changes. Debounce
            // so arrow-key spamming doesn't fire a request per intermediate row.
            this.$watch('highlightedId', () => this.scheduleLoadActions());
        },

        destroy() {
            if (this._morphHandler) {
                document.removeEventListener('livewire:morphed', this._morphHandler);
                document.removeEventListener('livewire:update', this._morphHandler);
            }
        },

        open() {
            if (this.isOpen) return;
            this.previouslyFocused = document.activeElement;
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                this.$refs.input?.focus();
                this.recomputeAnnouncement(true);
                this.ensureHighlight();
            });
        },

        close() {
            if (!this.isOpen) return;
            this.isOpen = false;
            document.body.style.overflow = '';
            this.highlightedId = null;
            this.resultsAnnouncement = '';
            this.openSubmenuFor = null;
            this.submenuLoading = false;
            if (this.previouslyFocused?.focus) {
                this.previouslyFocused.focus();
            }
        },

        // Esc: if focus is inside the submenu, return to input; else close palette.
        handleEscape() {
            const active = document.activeElement;
            const inSubmenu = active && active.closest('[data-spotlight-submenu]');
            if (inSubmenu) {
                this.returnFocusToInput();
                return;
            }
            this.close();
        },

        handleGlobalKey(event) {
            if (!matchesShortcut(event, this.binding)) return;

            const target = event.target;
            const inField = target && (
                SHORTCUT_INPUT_TARGETS.includes(target.tagName)
                || target.isContentEditable
            );
            // Allow opening from inside form fields only when modifier truly matches.
            if (inField && !this.isOpen && this.binding.mod && !(event.metaKey || event.ctrlKey)) {
                return;
            }

            event.preventDefault();
            this.isOpen ? this.close() : this.open();
        },

        // ArrowDown / ArrowUp / Enter / Home / End / Tab on the search input.
        handleListKey(event) {
            if (!this.isOpen) return;

            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this.moveHighlight(1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    this.moveHighlight(-1);
                    break;
                case 'Home':
                    event.preventDefault();
                    this.moveHighlightTo(0);
                    break;
                case 'End':
                    event.preventDefault();
                    this.moveHighlightTo(-1);
                    break;
                case 'Enter':
                    if (this.activateHighlighted()) {
                        event.preventDefault();
                    }
                    break;
                case 'Tab':
                    if (this.focusFirstSubmenuItem()) {
                        event.preventDefault();
                    }
                    break;
            }
        },

        // Debounced request to fetch actions for the currently highlighted row.
        // Fires whenever the highlight changes (keyboard or mouse hover).
        scheduleLoadActions() {
            if (this._loadActionsTimer) {
                clearTimeout(this._loadActionsTimer);
            }
            this._loadActionsTimer = setTimeout(() => this.loadActionsForCurrentRow(), 80);
        },

        loadActionsForCurrentRow() {
            if (!this.isOpen) return;
            const row = this.highlightedRowElement();
            if (!row) return;

            const resultId = row.getAttribute('data-spotlight-result-id');
            if (!resultId) return;
            if (this.openSubmenuFor === resultId) return;

            // Read payload only when the row advertises actions. Rows without
            // actions still need the server-side highlight sync so the submenu
            // from the previously-highlighted row closes when we move off it.
            const hasActions = row.getAttribute('data-spotlight-has-actions') === '1';
            let payload = {};
            if (hasActions) {
                const raw = row.getAttribute('data-spotlight-payload');
                if (raw) {
                    try { payload = JSON.parse(raw); } catch (_) { payload = {}; }
                }
            }

            this.submenuLoading = hasActions;
            this.openSubmenuFor = resultId;
            const promise = this.$wire.loadActionsForFocused(resultId, payload);
            const finish = () => { this.submenuLoading = false; };
            if (promise && typeof promise.then === 'function') {
                promise.then(finish).catch(finish);
            } else {
                finish();
            }
        },

        // Esc from inside the submenu: return focus to the search input so
        // keyboard nav resumes (highlight + submenu remain visible).
        returnFocusToInput() {
            this.$nextTick(() => this.$refs.input?.focus());
        },

        submenuItems() {
            const modal = this.$refs.modal;
            if (!modal) return [];
            const container = modal.querySelector('[data-spotlight-submenu]');
            if (!container) return [];
            return Array.from(container.querySelectorAll(
                'button:not([disabled]),a[href],[role="menuitem"]'
            )).filter((el) => el.offsetParent !== null);
        },

        focusFirstSubmenuItem() {
            const items = this.submenuItems();
            if (!items.length) return false;
            items[0].focus();
            return true;
        },

        moveSubmenu(delta) {
            const items = this.submenuItems();
            if (!items.length) return;
            const active = document.activeElement;
            const current = items.indexOf(active);
            const len = items.length;
            const next = current === -1
                ? (delta > 0 ? 0 : len - 1)
                : (current + delta + len) % len;
            items[next].focus();
        },

        highlightedRowElement() {
            const rows = this.rowElements();
            return rows.find((el) => el.getAttribute('data-spotlight-row') === this.highlightedId) ?? null;
        },

        rowElements() {
            const modal = this.$refs.modal;
            return modal ? Array.from(modal.querySelectorAll('[data-spotlight-row]')) : [];
        },

        moveHighlight(delta) {
            const rows = this.rowElements();
            if (!rows.length) return;
            const ids = rows.map((el) => el.getAttribute('data-spotlight-row'));
            const current = ids.indexOf(this.highlightedId);
            const len = ids.length;
            const next = current === -1
                ? (delta > 0 ? 0 : len - 1)
                : (current + delta + len) % len;
            this.highlightedId = ids[next];
            this.scrollHighlightedIntoView();
        },

        moveHighlightTo(index) {
            const rows = this.rowElements();
            if (!rows.length) return;
            const ids = rows.map((el) => el.getAttribute('data-spotlight-row'));
            const target = index < 0 ? ids.length - 1 : Math.min(index, ids.length - 1);
            this.highlightedId = ids[target];
            this.scrollHighlightedIntoView();
        },

        scrollHighlightedIntoView() {
            this.$nextTick(() => {
                const id = this.highlightedRowDomId;
                if (!id) return;
                document.getElementById(id)?.scrollIntoView({ block: 'nearest' });
            });
        },

        activateHighlighted() {
            const rows = this.rowElements();
            if (!rows.length) return false;
            const target = this.highlightedId
                ? rows.find((el) => el.getAttribute('data-spotlight-row') === this.highlightedId)
                : rows[0];
            if (!target) return false;
            target.click();
            return true;
        },

        // Make sure something is always highlighted while the palette is open.
        // Drops a stale id when the row no longer exists (e.g. results changed
        // after a query update) and falls back to the first available row.
        ensureHighlight() {
            if (!this.isOpen) return;
            const rows = this.rowElements();
            if (!rows.length) {
                this.highlightedId = null;
                return;
            }
            const ids = rows.map((el) => el.getAttribute('data-spotlight-row'));
            if (!this.highlightedId || !ids.includes(this.highlightedId)) {
                this.highlightedId = ids[0];
            }
        },

        // Minimal focus trap: cycle Tab/Shift+Tab at modal boundaries.
        trapTab(event) {
            if (!this.isOpen) return;
            const modal = this.$refs.modal;
            if (!modal) return;
            const focusable = Array.from(modal.querySelectorAll(FOCUSABLE_SELECTOR))
                .filter((el) => !el.hasAttribute('disabled') && el.offsetParent !== null);
            if (!focusable.length) return;

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const active = document.activeElement;

            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        },

        recomputeAnnouncement(justOpened = false) {
            const modal = this.$refs.modal;
            if (!modal) {
                this.resultsAnnouncement = '';
                return;
            }
            const rows = modal.querySelectorAll('[data-spotlight-row]');
            const groups = modal.querySelectorAll('[role="group"]');
            const count = rows.length;
            const groupCount = groups.length;
            const cfg = window.spotlightConfig?.announcements ?? {};

            if (count === 0) {
                this.resultsAnnouncement = cfg.empty || 'No results.';
                return;
            }
            if (count === 1) {
                this.resultsAnnouncement = cfg.singular || '1 result.';
                return;
            }

            const template = cfg.summary || ':count results in :groups groups.';
            this.resultsAnnouncement = template
                .replace(':count', String(count))
                .replace(':groups', String(groupCount));

            if (justOpened && cfg.opened) {
                // Prepend opened cue so the user hears both transitions.
                this.resultsAnnouncement = cfg.opened + ' ' + this.resultsAnnouncement;
            }
        },

        dispatchResult(payload) {
            // Server owns both recents capture and directive dispatch — one round-trip.
            this.$wire.activate(payload);
        },

        /**
         * Consumes the server-validated `spotlight:dispatch` event.
         */
        dispatch(directive) {
            if (!directive || typeof directive !== 'object') return;

            switch (directive.type) {
                case 'url':
                    this.handleUrlDirective(directive);
                    break;
                case 'event':
                    window.dispatchEvent(new CustomEvent(directive.name, {
                        detail: directive.payload || {},
                    }));
                    this.close();
                    break;
                case 'modal':
                    if (window.Livewire) {
                        window.Livewire.dispatch('spotlight:open-modal', directive);
                    }
                    this.close();
                    break;
                case 'callback':
                    if (window.Livewire) {
                        window.Livewire.dispatch('spotlight:source-callback', directive);
                    }
                    this.close();
                    break;
            }
        },

        handleUrlDirective(directive) {
            const url = directive.url;
            const newTab = directive.target === '_blank';
            this.close();
            if (newTab) {
                window.open(url, '_blank', 'noopener');
                return;
            }

            // Prefer Livewire SPA navigation when Filament's `->spa()` mode is on.
            // Falls back to a full page load when navigate is unavailable.
            if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                window.Livewire.navigate(url);
                return;
            }

            window.location.href = url;
        },
    };
}

if (typeof window !== 'undefined') {
    window.spotlight = spotlight;

    const registerWithAlpine = () => {
        if (!window.Alpine || typeof window.Alpine.data !== 'function') {
            return false;
        }
        window.Alpine.data('spotlight', spotlight);
        return true;
    };

    // Alpine is loaded by Filament before our module. Listen for `alpine:init`
    // (the canonical registration hook) AND register immediately if Alpine
    // already exposed `data()` synchronously.
    document.addEventListener('alpine:init', registerWithAlpine);
    if (window.Alpine?.version) {
        registerWithAlpine();
    }
}

export default spotlight;
export { parseShortcut, matchesShortcut };
