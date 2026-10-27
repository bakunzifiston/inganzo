<x-layouts.dashboard :title="__('Moderation')" area="admin">
    <x-ui.page-header :title="__('Design moderation')" :description="__('Unapproved designs are never visible to customers. Review originality, rights, content rules and print quality before publishing.')">
        <x-slot:actions>
            <form method="get" class="flex gap-2"><input type="hidden" name="status" value="{{ $status }}"><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search titles') }}" class="input w-48"></form>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="mb-6 flex flex-wrap gap-2 text-sm">
        @foreach (\App\Enums\ArtworkStatus::cases() as $s)
            <a href="{{ route('admin.moderation.index', ['status' => $s->value]) }}" @class(['rounded-full px-3 py-1.5 font-medium', 'bg-brand-600 text-ink' => $status === $s->value, 'bg-white ring-1 ring-line' => $status !== $s->value])>{{ $s->label() }} <span class="opacity-60">{{ $counts[$s->value] ?? 0 }}</span></a>
        @endforeach
    </div>
    @if ($artworks->isEmpty())
        <x-ui.empty icon="check-circle" :title="__('Nothing here')" :description="$status === 'pending_review' ? __('The review queue is empty.') : __('No designs with this status.')" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($artworks as $artwork)
                <a href="{{ route('admin.moderation.show', $artwork) }}" class="card group overflow-hidden transition hover:shadow-[var(--shadow-lift)]">
                    <div class="aspect-square bg-checker p-4">
                        @if ($artwork->currentVersion?->isReady())<img src="{{ $artwork->currentVersion->thumbnailUrl() }}" alt="" class="h-full w-full object-contain" loading="lazy">@endif
                    </div>
                    <div class="p-4">
                        <p class="truncate font-semibold group-hover:text-brand-700">{{ $artwork->title }}</p>
                        <p class="truncate text-xs text-muted">{{ $artwork->artist->display_name }} · {{ $artwork->category?->translate('name') ?? __('No category') }}</p>
                        <div class="mt-2 flex items-center justify-between text-xs">
                            <x-ui.badge :status="$artwork->artist->status" />
                            <span class="text-subtle">{{ trans_choice(':count product|:count products', $artwork->enabled_products_count, ['count' => $artwork->enabled_products_count]) }}</span>
                        </div>
                        @if ($artwork->submitted_at)<p class="mt-2 text-xs text-subtle">{{ __('Submitted :time', ['time' => $artwork->submitted_at->diffForHumans()]) }}</p>@endif
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $artworks->links() }}</div>
    @endif
</x-layouts.dashboard>
