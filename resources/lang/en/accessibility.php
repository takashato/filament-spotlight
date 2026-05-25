<?php

declare(strict_types=1);

return [
    // Announced when the palette becomes visible.
    'palette_opened' => 'Search palette opened. Use up and down arrows to navigate, Enter to open, Escape to close.',
    'palette_closed' => 'Search palette closed.',

    // Live-region announcement updated as results change.
    // :count = total result count, :groups = number of source groups.
    'results_summary' => ':count results in :groups groups.',
    'results_summary_singular' => '1 result.',
    'results_empty' => 'No results.',

    // Spoken when the highlighted row changes (used optionally by assistive tech via aria-activedescendant).
    'result_highlighted' => 'Highlighted: :title.',

    // Loading state.
    'loading' => 'Searching…',
];
