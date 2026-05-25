<?php

declare(strict_types=1);

/**
 * Asserts that the four spotlight translation namespaces stay in lockstep
 * across en + vi. A drift here is a hard CI failure — strings ship together
 * or not at all.
 */
$namespaces = ['spotlight', 'sources', 'recents', 'accessibility'];
$base = __DIR__.'/../../resources/lang';

dataset('namespaces', array_map(static fn (string $n): array => [$n], $namespaces));

it('keeps the en + vi key sets in lockstep for namespace :namespace', function (string $namespace) use ($base): void {
    $en = require $base.'/en/'.$namespace.'.php';
    $vi = require $base.'/vi/'.$namespace.'.php';

    expect($en)->toBeArray()->and($vi)->toBeArray();

    $enKeys = array_keys($en);
    $viKeys = array_keys($vi);
    sort($enKeys);
    sort($viKeys);

    expect($viKeys)->toBe(
        $enKeys,
        sprintf('Locale parity drift in `%s` namespace.', $namespace),
    );
})->with('namespaces');

it('resolves the spotlight UI keys via the package translator', function (): void {
    expect(__('spotlight::spotlight.search_placeholder'))->not->toBe('spotlight::spotlight.search_placeholder');
    expect(__('spotlight::spotlight.trigger_label'))->not->toBe('spotlight::spotlight.trigger_label');
});

it('resolves the accessibility namespace via the package translator', function (): void {
    expect(__('spotlight::accessibility.results_summary'))->not->toBe('spotlight::accessibility.results_summary');
    expect(__('spotlight::accessibility.palette_opened'))->not->toBe('spotlight::accessibility.palette_opened');
});
