<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Support;

/**
 * Pure-PHP shortcut string parser. Mirrors the JS parser in `spotlight.js`.
 *
 * Accepts strings such as `mod+k`, `ctrl+shift+p`, `cmd+alt+/`, `meta+k`.
 * `mod` collapses platform differences (Cmd on macOS, Ctrl elsewhere) and
 * matches `cmd`, `meta`, or `ctrl` interchangeably. `option` aliases `alt`.
 *
 * Returns a stable shape: `['mod' => bool, 'shift' => bool, 'alt' => bool, 'key' => string]`.
 *
 * Malformed / empty input falls back to `mod+k` to keep the palette reachable.
 */
final class KeyBindingParser
{
    /** @var list<string> */
    private const MOD_TOKENS = ['mod', 'cmd', 'meta', 'ctrl', 'control'];

    /** @var list<string> */
    private const ALT_TOKENS = ['alt', 'option', 'opt'];

    private const SHIFT_TOKEN = 'shift';

    /**
     * @return array{mod: bool, shift: bool, alt: bool, key: string}
     */
    public static function parse(?string $raw): array
    {
        $value = is_string($raw) ? strtolower(trim($raw)) : '';

        if ($value === '') {
            return self::default();
        }

        $parts = array_values(array_filter(
            array_map(static fn (string $part): string => trim($part), explode('+', $value)),
            static fn (string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            return self::default();
        }

        $key = (string) array_pop($parts);

        // Defensive: `mod+` (trailing separator) leaves the key empty.
        if ($key === '' || in_array($key, self::MOD_TOKENS, true) || in_array($key, self::ALT_TOKENS, true) || $key === self::SHIFT_TOKEN) {
            return self::default();
        }

        $mod = false;
        $alt = false;
        $shift = false;

        foreach ($parts as $token) {
            if (in_array($token, self::MOD_TOKENS, true)) {
                $mod = true;

                continue;
            }
            if (in_array($token, self::ALT_TOKENS, true)) {
                $alt = true;

                continue;
            }
            if ($token === self::SHIFT_TOKEN) {
                $shift = true;
            }
            // Unknown modifier tokens are silently ignored to keep parsing total.
        }

        return [
            'mod' => $mod,
            'shift' => $shift,
            'alt' => $alt,
            'key' => $key,
        ];
    }

    /**
     * @return array{mod: bool, shift: bool, alt: bool, key: string}
     */
    private static function default(): array
    {
        return ['mod' => true, 'shift' => false, 'alt' => false, 'key' => 'k'];
    }
}
