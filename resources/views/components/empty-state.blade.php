@props([
    'query' => '',
])

<div class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400" role="status">
    @if (trim($query) === '')
        <p>{{ __('spotlight::spotlight.empty_no_query') }}</p>
    @else
        <p>{{ __('spotlight::spotlight.empty_no_results', ['query' => $query]) }}</p>
    @endif
</div>
