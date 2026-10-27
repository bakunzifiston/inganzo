<x-layouts.dashboard :title="__('Designs')" area="studio">
    <x-ui.page-header :title="__('Designs')" :description="__('Upload artwork once and offer it on every product.')">
        <x-slot:actions>
            <x-ui.button :href="route('studio.design.create')" variant="secondary"><x-ui.icon name="pencil" class="size-4" /> {{ __('Create in Design Studio') }}</x-ui.button>
            <x-ui.button :href="route('studio.artworks.create')" variant="accent"><x-ui.icon name="upload" class="size-4" /> {{ __('Upload design') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        <a href="{{ route('studio.artworks.index') }}" @class(['rounded-full px-3.5 py-1.5 text-sm font-medium', 'bg-brand-600 text-ink' => ! $status, 'bg-white ring-1 ring-line hover:ring-ink' => $status])>{{ __('All') }} <span class="opacity-60">{{ $counts->sum() }}</span></a>
        @foreach (\App\Enums\ArtworkStatus::cases() as $case)
            @if (($counts[$case->value] ?? 0) > 0)
                <a href="{{ route('studio.artworks.index', ['status' => $case->value]) }}" @class(['rounded-full px-3.5 py-1.5 text-sm font-medium', 'bg-brand-600 text-ink' => $status === $case->value, 'bg-white ring-1 ring-line hover:ring-ink' => $status !== $case->value])>{{ $case->label() }} <span class="opacity-60">{{ $counts[$case->value] }}</span></a>
            @endif
        @endforeach
        <form method="get" class="ml-auto"><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search titles') }}" class="input w-56" aria-label="{{ __('Search titles') }}"></form>
    </div>

    @if ($artworks->isEmpty())
        <x-ui.empty icon="image" :title="__('No designs yet')" :description="__('Upload a PNG with a transparent background — at least 3000 px wide gives the best prints.')">
            <x-ui.button :href="route('studio.artworks.create')" variant="accent">{{ __('Upload your first design') }}</x-ui.button>
        </x-ui.empty>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($artworks as $artwork)
                <a href="{{ route('studio.artworks.show', $artwork) }}" class="group card overflow-hidden transition hover:shadow-[var(--shadow-lift)]">
                    <div class="aspect-[4/3] bg-checker p-6">
                        @if ($artwork->currentVersion?->isReady())
                            <img src="{{ $artwork->currentVersion->thumbnailUrl() }}" alt="{{ $artwork->title }}" class="h-full w-full object-contain transition group-hover:scale-105" loading="lazy">
                        @elseif (! $artwork->current_version_id)
                            <div class="grid h-full place-items-center text-sm text-muted">{{ __('Not exported yet') }}</div>
                        @else
                            <div class="grid h-full place-items-center text-sm text-muted">{{ __('Processing…') }}</div>
                        @endif
                    </div>
                    <div class="flex items-start justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $artwork->title }}</p>
                            <p class="text-xs text-muted">{{ trans_choice(':count product|:count products', $artwork->enabled_products_count, ['count' => $artwork->enabled_products_count]) }} · {{ $artwork->updated_at->diffForHumans() }}</p>
                        </div>
                        <x-ui.badge :status="$artwork->status" />
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-8">{{ $artworks->links() }}</div>
    @endif
</x-layouts.dashboard>
