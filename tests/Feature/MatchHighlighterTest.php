<?php

declare(strict_types=1);

use Takashato\FilamentSpotlight\Support\MatchHighlighter;

it('returns escaped title untouched when query is empty', function (): void {
    $out = MatchHighlighter::highlight('Shipments', '');
    expect($out)->toBe('Shipments');
});

it('wraps matched substring with mark and preserves casing', function (): void {
    $out = MatchHighlighter::highlight('Shipments overview', 'ship');
    expect($out)->toContain('<mark>Ship</mark>');
});

it('escapes html special chars in title before wrapping mark', function (): void {
    $payload = '<script>alert("xss")</script>';
    $out = MatchHighlighter::highlight($payload, 'script');

    expect($out)->not->toContain('<script>');
    expect($out)->toContain('&lt;');
    expect($out)->toContain('<mark>script</mark>');
});

it('escapes the query before regex matching to prevent injection', function (): void {
    $out = MatchHighlighter::highlight('Hello world', '/.*/');
    // Pattern is properly escaped; no match for literal "/.*/"
    expect($out)->toBe('Hello world');
});

it('matches case-insensitive with multibyte input', function (): void {
    $out = MatchHighlighter::highlight('Tài Khoản', 'tài');
    expect($out)->toContain('<mark>Tài</mark>');
});
