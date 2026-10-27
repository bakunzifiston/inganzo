<x-layouts.shop :title="$artist->display_name" :description="$artist->tagline ?? \Illuminate\Support\Str::limit($artist->bio, 150)">
    <div class="relative h-48 overflow-hidden bg-ink sm:h-64">
        @if ($artist->bannerUrl())
            <img src="{{ $artist->bannerUrl() }}" alt="" class="h-full w-full object-cover">
        @else
            <div class="absolute inset-0 opacity-30 imigongo-band-clay" style="height: 100%; background-size: 44px 44px"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-ink to-transparent"></div>
        @endif
    </div>
    <div class="container-page">
        <div class="-mt-14 flex flex-wrap items-end gap-6 sm:-mt-16">
            <x-shop.avatar :artist="$artist" class="size-28 border-4 border-paper text-4xl sm:size-32" />
            <div class="pb-2">
                <h1 class="text-3xl font-bold">{{ $artist->display_name }}</h1>
                <p class="text-muted">{{ $artist->tagline }}@if ($artist->location) · {{ $artist->location }}@endif</p>
            </div>
            <div class="ml-auto flex flex-wrap gap-2 pb-3 text-sm" x-data="{ copied: false }">
                @if ($artist->instagram_handle)<a href="https://instagram.com/{{ ltrim($artist->instagram_handle, '@') }}" target="_blank" rel="noopener nofollow" class="rounded-full border border-line-strong px-4 py-2 hover:border-ink">Instagram</a>@endif
                @if ($artist->x_handle)<a href="https://x.com/{{ ltrim($artist->x_handle, '@') }}" target="_blank" rel="noopener nofollow" class="rounded-full border border-line-strong px-4 py-2 hover:border-ink">X</a>@endif
                @if ($artist->website_url)<a href="{{ $artist->website_url }}" target="_blank" rel="noopener nofollow" class="rounded-full border border-line-strong px-4 py-2 hover:border-ink">{{ __('Website') }}</a>@endif
                <button type="button" class="rounded-full border border-line-strong px-4 py-2 hover:border-ink" @click="navigator.clipboard.writeText('{{ route('artists.show', $artist) }}'); copied = true">
                    <span x-show="! copied">{{ __('Share shop') }}</span><span x-cloak x-show="copied">{{ __('Link copied') }}</span>
                </button>
            </div>
        </div>

        <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_280px]">
            <div>
                @if ($bestSellers->isNotEmpty() && ! $activeType && $products->currentPage() === 1)
                    <h2 class="mb-5 text-xl font-bold">{{ __('Best sellers') }}</h2>
                    <div class="mb-12 grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-4">
                        @foreach ($bestSellers as $item)<x-shop.product-card :item="$item" :show-artist="false" />@endforeach
                    </div>
                @endif

                <div class="mb-5 flex flex-wrap items-center gap-2">
                    <a href="{{ route('artists.show', $artist) }}" @class(['rounded-full px-4 py-1.5 text-sm font-medium', 'bg-brand-600 text-ink' => ! $activeType, 'bg-sand hover:bg-line' => $activeType])>{{ __('All') }}</a>
                    <a href="{{ route('artists.show', [$artist, 'type' => \App\Services\Catalog\CatalogSearch::READY_MADE]) }}" @class(['rounded-full px-4 py-1.5 text-sm font-medium', 'bg-brand-600 text-ink' => $activeType === \App\Services\Catalog\CatalogSearch::READY_MADE, 'bg-sand hover:bg-line' => $activeType !== \App\Services\Catalog\CatalogSearch::READY_MADE])>{{ __('Ready-made') }}</a>
                    @foreach ($types as $type)
                        <a href="{{ route('artists.show', [$artist, 'type' => $type->slug]) }}" @class(['rounded-full px-4 py-1.5 text-sm font-medium', 'bg-brand-600 text-ink' => $activeType === $type->slug, 'bg-sand hover:bg-line' => $activeType !== $type->slug])>{{ $type->translate('name') }}</a>
                    @endforeach
                </div>

                @if ($products->isEmpty())
                    <x-ui.empty :title="__('Nothing here yet')" :description="__('This artist has no products in this category.')" />
                @else
                    <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3">
                        @foreach ($products as $item)<x-shop.product-card :item="$item" :show-artist="false" />@endforeach
                    </div>
                    <div class="mt-10">{{ $products->links() }}</div>
                @endif
            </div>
            <aside class="space-y-6">
                <div class="card p-5">
                    <h2 class="font-semibold">{{ __('About') }}</h2>
                    <p class="mt-2 text-sm whitespace-pre-line text-muted">{{ $artist->bio ?: __('This artist has not written a bio yet.') }}</p>
                    <p class="mt-4 text-xs text-subtle">{{ trans_choice(':count published design|:count published designs', $artist->published_artworks_count, ['count' => $artist->published_artworks_count]) }}</p>
                </div>
                @if ($collections->isNotEmpty())
                    <div class="card p-5">
                        <h2 class="font-semibold">{{ __('Collections') }}</h2>
                        <ul class="mt-3 space-y-2 text-sm">
                            @foreach ($collections as $collection)
                                <li><a href="{{ route('shop.collection', $collection) }}" class="flex justify-between hover:text-brand-700"><span>{{ $collection->translate('title') }}</span><span class="text-subtle">{{ $collection->artworks_count }}</span></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</x-layouts.shop>
