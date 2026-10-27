@props(['artist'])
<a href="{{ route('artists.show', $artist) }}" {{ $attributes->class('group card flex items-center gap-4 p-4 transition hover:shadow-[var(--shadow-lift)]') }}>
    <x-shop.avatar :artist="$artist" class="size-14" />
    <div class="min-w-0">
        <p class="truncate font-semibold group-hover:text-brand-700">{{ $artist->display_name }}</p>
        <p class="truncate text-xs text-muted">{{ $artist->tagline ?? $artist->location ?? __('Independent artist') }}</p>
        @isset($artist->published_artworks_count)
            <p class="mt-1 text-xs text-subtle">{{ trans_choice(':count design|:count designs', $artist->published_artworks_count, ['count' => $artist->published_artworks_count]) }}</p>
        @endisset
    </div>
</a>
