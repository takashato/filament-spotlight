<?php

declare(strict_types=1);

use Takashato\FilamentSpotlight\Sources\FilamentResourceSource;
use Takashato\FilamentSpotlight\Sources\NavigationSource;

return [

    'shortcut' => [
        'keys' => 'mod+k',
        'override_filament' => true,
        'fallback' => 'mod+shift+k',
    ],

    'limits' => [
        'per_source' => 5,
        'total' => 20,
        'per_source_timeout_ms' => 500,
    ],

    'debounce_ms' => 200,

    /*
     * Class-keyed map. Keys are source class names; values are per-source overrides.
     * Set value to `null` to disable a default source. Setting a value of `false` is also accepted.
     * Phase 3 wires concrete source classes; for now the entries below act as placeholders.
     */
    'sources' => [
        FilamentResourceSource::class => ['priority' => 100],
        NavigationSource::class => ['priority' => 90],
    ],

    'recents' => [
        'enabled' => true,
        'cap_per_user' => 50,
        'show_in_empty_state' => 5,
    ],

    'mobile_breakpoint' => 'md',

    'experimental' => [
        'unified_ranking' => false,
    ],

];
