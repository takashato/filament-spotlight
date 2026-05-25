<?php

declare(strict_types=1);

namespace Takashato\FilamentSpotlight\Support;

/**
 * Wraps the matched substring of a title in `<mark>` tags for UI highlighting.
 *
 * SECURITY: Inputs are HTML-escaped BEFORE the `<mark>` wrapper is inserted.
 * Sources MUST never bypass this helper for user-derived titles.
 */
final class MatchHighlighter
{
    /**
     * Returns an HTML-safe string. Matches are case-insensitive; original
     * casing of the title is preserved. Empty queries return the escaped title.
     */
    public static function highlight(string $title, string $query): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $trimmed = trim($query);

        if ($trimmed === '') {
            return $escapedTitle;
        }

        $escapedQuery = htmlspecialchars($trimmed, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pattern = '/'.preg_quote($escapedQuery, '/').'/iu';

        $replaced = preg_replace_callback(
            $pattern,
            static fn (array $m): string => '<mark>'.$m[0].'</mark>',
            $escapedTitle,
        );

        return is_string($replaced) ? $replaced : $escapedTitle;
    }
}
