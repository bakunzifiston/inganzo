@props(['item', 'showArtist' => true])
@if ($item instanceof \App\Models\FinishedProduct)
    @php $cover = $item->coverImage(); $soldOut = ! $item->isInStock(); @endphp
    <article {{ $attributes->class('group relative') }}>
        <a href="{{ route('finished.show', $item) }}" class="block">
            <div class="relative aspect-square overflow-hidden rounded-2xl bg-sand transition duration-300 group-hover:shadow-[var(--shadow-lift)]">
                @if ($cover)<img src="{{ $cover->thumbnailUrl() }}" alt="{{ $item->title }}" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]">@endif
                <span class="absolute top-3 left-3 rounded-full bg-white/95 px-2.5 py-0.5 text-[11px] font-semibold text-ink shadow-sm">{{ $item->is_handmade ? __('Handmade') : __('Ready-made') }}</span>
                @if ($soldOut)<span class="absolute inset-x-0 bottom-0 bg-ink/75 py-1.5 text-center text-xs font-semibold text-white">{{ __('Out of stock') }}</span>
                @elseif ($item->isOnSale())<span class="absolute top-3 right-3 rounded-full bg-danger-700 px-2.5 py-0.5 text-[11px] font-semibold text-white">{{ __('Sale') }}</span>@endif
            </div>
            <div class="mt-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-semibold text-ink">{{ $item->title }}</h3>
                    <p class="truncate text-xs text-muted">@if ($showArtist){{ $item->artist->display_name }}@else{{ __('Ready-made') }}@endif</p>
                </div>
                @if ($item->price_from_amount)<p class="shrink-0 text-sm font-semibold"><x-ui.money :amount="$item->price_from_amount" :currency="$item->currency" /></p>@endif
            </div>
        </a>
    </article>
@else
@php
    /** @var \App\Models\ArtistProduct $item */
    $artwork = $item->artwork;
    $color = $item->displayColor();
@endphp
<article {{ $attributes->class('group relative') }}>
    <a href="{{ route('products.show', [$artwork, $item->product]) }}" class="block">
        <div @class([
            'relative aspect-square overflow-hidden rounded-2xl transition duration-300 group-hover:shadow-[var(--shadow-lift)]',
            'bg-sand' => ! $color?->is_dark,
            'bg-mist' => $color?->is_dark,
        ])>
            <x-shop.mockup :product="$item->product" :color="$color" :version="$artwork->currentVersion" :placements="$item->placements"
                class="h-full w-full p-3 transition duration-500 group-hover:scale-[1.03]" :label="$artwork->title.' — '.$item->product->translate('name')" />
            @if ($item->colors->count() > 1)
                <div class="absolute bottom-3 left-3 flex -space-x-1">
                    @foreach ($item->colors->take(5) as $swatch)
                        <span class="size-3.5 rounded-full border border-black/25 ring-2 ring-white" style="background: {{ $swatch->hex }}" title="{{ $swatch->name }}"></span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="mt-3 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h3 class="truncate text-sm font-semibold text-ink">{{ $artwork->title }}</h3>
                <p class="truncate text-xs text-muted">{{ $item->product->translate('name') }}@if ($showArtist) · {{ $artwork->artist->display_name }}@endif</p>
            </div>
            @if ($item->price_from_amount)
                <p class="shrink-0 text-sm font-semibold"><x-ui.money :amount="$item->price_from_amount" :currency="$item->currency" /></p>
            @endif
        </div>
    </a>
</article>
@endif
