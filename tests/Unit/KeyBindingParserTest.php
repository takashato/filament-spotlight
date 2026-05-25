<?php

declare(strict_types=1);

use Takashato\FilamentSpotlight\Support\KeyBindingParser;

it('parses mod+k as the canonical default', function (): void {
    expect(KeyBindingParser::parse('mod+k'))
        ->toBe(['mod' => true, 'shift' => false, 'alt' => false, 'key' => 'k']);
});

it('treats cmd+k as a mod+k alias', function (): void {
    expect(KeyBindingParser::parse('cmd+k'))
        ->toMatchArray(['mod' => true, 'key' => 'k']);
});

it('treats meta+k as a mod+k alias', function (): void {
    expect(KeyBindingParser::parse('meta+k'))
        ->toMatchArray(['mod' => true, 'key' => 'k']);
});

it('treats ctrl+k as a mod+k alias', function (): void {
    expect(KeyBindingParser::parse('ctrl+k'))
        ->toMatchArray(['mod' => true, 'key' => 'k']);
});

it('parses mod+shift+k', function (): void {
    expect(KeyBindingParser::parse('mod+shift+k'))
        ->toBe(['mod' => true, 'shift' => true, 'alt' => false, 'key' => 'k']);
});

it('parses mod+alt+k and accepts option as an alt alias', function (): void {
    expect(KeyBindingParser::parse('mod+alt+k'))->toBe(
        ['mod' => true, 'shift' => false, 'alt' => true, 'key' => 'k'],
    );

    expect(KeyBindingParser::parse('cmd+option+/'))->toBe(
        ['mod' => true, 'shift' => false, 'alt' => true, 'key' => '/'],
    );
});

it('accepts a bare key without modifiers', function (): void {
    expect(KeyBindingParser::parse('k'))
        ->toBe(['mod' => false, 'shift' => false, 'alt' => false, 'key' => 'k']);
});

it('parses mod+shift+alt+k correctly', function (): void {
    expect(KeyBindingParser::parse('mod+shift+alt+k'))
        ->toBe(['mod' => true, 'shift' => true, 'alt' => true, 'key' => 'k']);
});

it('falls back to mod+k for malformed input', function (string $input): void {
    expect(KeyBindingParser::parse($input))->toBe([
        'mod' => true, 'shift' => false, 'alt' => false, 'key' => 'k',
    ]);
})->with([
    [''],
    ['   '],
    ['+++'],
    ['mod+'],
    ['mod+shift'], // trailing modifier with no key
    ['shift'],
]);

it('returns the default when input is null', function (): void {
    expect(KeyBindingParser::parse(null))
        ->toBe(['mod' => true, 'shift' => false, 'alt' => false, 'key' => 'k']);
});

it('lowercases case-mixed input', function (): void {
    expect(KeyBindingParser::parse('Mod+Shift+P'))
        ->toBe(['mod' => true, 'shift' => true, 'alt' => false, 'key' => 'p']);
});

it('ignores unknown tokens silently', function (): void {
    expect(KeyBindingParser::parse('mod+hyper+k'))
        ->toBe(['mod' => true, 'shift' => false, 'alt' => false, 'key' => 'k']);
});
