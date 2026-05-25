@props([
    'sourceKey' => '',
    'results' => collect(),
    'highlightedId' => null,
])

@php
    use Illuminate\Support\Str;
    $registry = app(\Takashato\FilamentSpotlight\Spotlight::class);
    $source = $registry->sources()->first(fn ($s) => $s->key() === $sourceKey);
    if ($sourceKey === 'recents') {
        $groupLabel = __('spotlight::recents.title');
        $groupIcon = 'heroicon-o-clock';
    } else {
        $groupLabel = $source?->label() ?? Str::headline($sourceKey);
        $groupIcon = $source?->icon();
    }
@endphp

<div class="spotlight-group" role="group" aria-label="{{ $groupLabel }}">
    <div class="flex items-center gap-2 px-4 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" aria-hidden="true">
        @if ($groupIcon)
            <x-filament::icon :icon="$groupIcon" class="h-3.5 w-3.5" />
        @endif
        <span>{{ $groupLabel }}</span>
    </div>
    {{-- ARIA INVARIANT: rows render as role="option" inside the group; the parent --}}
    {{-- listbox lives in palette.blade.php. <ul role="presentation"> keeps the --}}
    {{-- listbox flat from an a11y tree perspective. --}}
    <ul role="presentation" class="pb-1">
        @foreach ($results as $result)
            <x-spotlight::result
                :result="$result"
                :is-highlighted="$highlightedId === $result->sourceKey().'::'.$result->id()"
            />
        @endforeach
    </ul>
</div>
