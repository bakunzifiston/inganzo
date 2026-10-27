@php
    $initialColor = $artistProduct->displayColor()?->id;
    $hasBack = $product->printAreas->contains(fn ($area) => $area->key === 'back' && $artistProduct->placementFor($area));
    $views = $hasBack ? ['front', 'back'] : ['front'];
    $productionDays = $product->production_days;
    $shareUrl = route('products.show', [$artwork, $product]);
@endphp
<x-layouts.shop :title="$artwork->title.' — '.$product->translate('name')" :description="\Illuminate\Support\Str::limit($artwork->description ?? __('Original design by :artist', ['artist' => $artwork->artist->display_name]), 150)">
    <div class="container-page py-8 lg:py-12"
        x-data="variantPicker(@js($variants), @js($artistProduct->colors->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'hex' => $c->hex])->values()), {{ $initialColor ?? 'null' }})">
        <nav class="mb-6 text-xs text-muted" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('home') }}" class="hover:text-ink">{{ __('Home') }}</a> /
            <a href="{{ route('shop.index', ['type' => $product->type->slug]) }}" class="hover:text-ink">{{ $product->type->translate('name') }}</a> /
            <span class="text-ink">{{ $artwork->title }}</span>
        </nav>

        <div class="grid gap-10 lg:grid-cols-[1.15fr_1fr] lg:gap-14">
            {{-- Gallery --}}
            <div x-data="{ view: 'front' }">
                <div class="relative aspect-square overflow-hidden rounded-3xl bg-sand">
                    @foreach ($artistProduct->colors as $color)
                        @foreach ($views as $view)
                            <div x-show="color === {{ $color->id }} && view === '{{ $view }}'" @if (! ($color->id === $initialColor && $view === 'front')) x-cloak @endif class="absolute inset-0 p-6 sm:p-10">
                                <x-shop.mockup :product="$product" :color="$color" :version="$artwork->currentVersion" :placements="$artistProduct->placements" :view="$view"
                                    :label="$artwork->title.' — '.$product->translate('name').' — '.$color->name" />
                            </div>
                        @endforeach
                    @endforeach
                </div>
                <div class="mt-4 flex gap-3">
                    @foreach ($views as $view)
                        <button type="button" @click="view = '{{ $view }}'" :class="view === '{{ $view }}' ? 'ring-2 ring-ink' : 'ring-1 ring-line'"
                            class="aspect-square w-20 overflow-hidden rounded-xl bg-sand p-1.5" aria-label="{{ $view === 'front' ? __('Front view') : __('Back view') }}">
                            <x-shop.mockup :product="$product" :color="$artistProduct->displayColor()" :version="$artwork->currentVersion" :placements="$artistProduct->placements" :view="$view" />
                        </button>
                    @endforeach
                    <div class="ml-auto aspect-square w-20 rounded-xl bg-white p-2 ring-1 ring-line" title="{{ __('Original artwork') }}">
                        <img src="{{ $artwork->currentVersion->thumbnailUrl() }}" alt="{{ __('Artwork: :title', ['title' => $artwork->title]) }}" class="h-full w-full object-contain">
                    </div>
                </div>
            </div>

            {{-- Purchase panel --}}
            <div class="lg:pt-2">
                <a href="{{ route('artists.show', $artwork->artist) }}" class="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-brand-700">
                    <x-shop.avatar :artist="$artwork->artist" class="size-7 text-xs" /> {{ $artwork->artist->display_name }}
                </a>
                <h1 class="mt-3 text-3xl font-bold sm:text-4xl">{{ $artwork->title }}</h1>
                <p class="mt-1 text-lg text-muted">{{ $product->translate('name') }}</p>

                <p class="mt-5 font-display text-3xl font-semibold">
                    <span x-text="variant ? '{{ $artistProduct->currency }} ' + variant.price.toLocaleString('en-US') : ''"></span>
                    <noscript><x-ui.money :amount="$artistProduct->price_from_amount" :currency="$artistProduct->currency" /></noscript>
                </p>
                <p class="mt-1 text-xs text-muted">{{ __('Price includes VAT. Delivery calculated at checkout.') }}</p>

                <form method="post" action="{{ route('cart.items.store') }}" class="mt-8 space-y-6">
                    @csrf
                    <input type="hidden" name="artist_product_id" value="{{ $artistProduct->id }}">
                    <input type="hidden" name="variant_id" :value="variant?.id">

                    <fieldset>
                        <legend class="text-sm font-semibold">{{ __('Colour') }}: <span class="font-normal text-muted" x-text="colorName"></span></legend>
                        <div class="mt-3 flex flex-wrap gap-2.5">
                            @foreach ($artistProduct->colors as $color)
                                <button type="button" @click="color = {{ $color->id }}; const s = sizesForColor(); if (s.length && ! s.find(x => x.id === size)) size = s[0].id"
                                    :class="color === {{ $color->id }} ? 'ring-2 ring-ink ring-offset-2' : 'ring-1 ring-line-strong'"
                                    class="size-10 rounded-full transition" style="background: {{ $color->hex }}" aria-label="{{ $color->name }}" :aria-pressed="color === {{ $color->id }}"></button>
                            @endforeach
                        </div>
                    </fieldset>

                    <template x-if="sizesForColor().length">
                        <fieldset>
                            <div class="flex items-center justify-between">
                                <legend class="text-sm font-semibold">{{ __('Size') }}</legend>
                                @if ($product->type->slug !== 'stickers')<a href="{{ route('pages.show', 'delivery') }}#sizes" class="text-xs text-muted underline">{{ __('Size guide') }}</a>@endif
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <template x-for="option in sizesForColor()" :key="option.id">
                                    <button type="button" @click="size = option.id" x-text="option.label"
                                        :class="size === option.id ? 'border-brand-600 bg-brand-600 text-ink' : 'border-line-strong bg-white hover:border-ink'"
                                        class="min-w-14 rounded-xl border px-4 py-2.5 text-sm font-semibold transition"></button>
                                </template>
                            </div>
                        </fieldset>
                    </template>

                    <div class="flex items-end gap-3">
                        <div x-data="{ qty: 1 }" class="w-32">
                            <label for="quantity" class="text-sm font-semibold">{{ __('Quantity') }}</label>
                            <div class="mt-2 flex items-center rounded-full border border-line-strong bg-white">
                                <button type="button" class="p-3" @click="qty = Math.max(1, qty - 1)" aria-label="{{ __('Decrease') }}"><x-ui.icon name="minus" class="size-4" /></button>
                                <input id="quantity" name="quantity" type="number" min="1" max="{{ config('marketplace.cart_max_quantity') }}" x-model.number="qty" class="w-full border-0 bg-transparent p-0 text-center text-sm font-semibold focus:ring-0">
                                <button type="button" class="p-3" @click="qty = Math.min({{ config('marketplace.cart_max_quantity') }}, qty + 1)" aria-label="{{ __('Increase') }}"><x-ui.icon name="plus" class="size-4" /></button>
                            </div>
                        </div>
                        <x-ui.button variant="accent" size="lg" class="flex-1" x-bind:disabled="! variant">
                            <x-ui.icon name="cart" class="size-5" /> {{ __('Add to cart') }}
                        </x-ui.button>
                    </div>
                    @error('variant')<p class="text-sm text-danger-700">{{ $message }}</p>@enderror
                </form>

                <div class="mt-4 flex items-center gap-4 text-sm">
                    @auth
                        <form method="post" action="{{ route('favorites.toggle', $artwork) }}">
                            @csrf
                            <button class="inline-flex items-center gap-2 font-medium {{ $isFavorite ? 'text-brand-700' : 'text-muted hover:text-ink' }}">
                                <x-ui.icon name="heart" class="size-5 {{ $isFavorite ? 'fill-brand-600' : '' }}" />
                                {{ $isFavorite ? __('Saved to favourites') : __('Save to favourites') }}
                            </button>
                        </form>
                    @endauth
                    <div x-data="{ copied: false }" class="ml-auto flex items-center gap-2">
                        <a href="https://wa.me/?text={{ urlencode($artwork->title.' — '.$shareUrl) }}" target="_blank" rel="noopener" class="rounded-full p-2 text-muted hover:bg-sand hover:text-ink" aria-label="{{ __('Share on WhatsApp') }}"><x-ui.icon name="share" class="size-4" /></a>
                        <button type="button" class="text-xs font-medium text-muted hover:text-ink" @click="navigator.clipboard.writeText('{{ $shareUrl }}'); copied = true; setTimeout(() => copied = false, 2000)">
                            <span x-show="! copied">{{ __('Copy link') }}</span><span x-cloak x-show="copied">{{ __('Copied!') }}</span>
                        </button>
                    </div>
                </div>

                <div class="mt-8 divide-y divide-line rounded-2xl border border-line bg-white">
                    <div class="flex gap-3 p-4">
                        <x-ui.icon name="printer" class="mt-0.5 size-5 shrink-0 text-success-600" />
                        <div class="text-sm">
                            <p class="font-semibold">{{ __('Printed to order in Rwanda') }}</p>
                            <p class="text-muted">{{ trans_choice('Usually ready to ship in :count business day.|Usually ready to ship in :count business days.', $productionDays, ['count' => $productionDays]) }}</p>
                        </div>
                    </div>
                    <div class="flex gap-3 p-4">
                        <x-ui.icon name="truck" class="mt-0.5 size-5 shrink-0 text-success-600" />
                        <div class="text-sm">
                            <p class="font-semibold">{{ __('Delivery') }}</p>
                            <ul class="text-muted">
                                @foreach ($deliveryZones as $zone)
                                    <li>{{ $zone->name }}: <x-ui.money :amount="$zone->fee_amount" /> · {{ $zone->estimateLabel() }}@if ($zone->free_over_amount) · {{ __('free over :amount', ['amount' => \App\Support\Money::format($zone->free_over_amount, 'RWF')]) }}@endif</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="flex gap-3 p-4">
                        <x-ui.icon name="shield" class="mt-0.5 size-5 shrink-0 text-success-600" />
                        <p class="text-sm text-muted">{{ __('Damaged or misprinted? We reprint or refund.') }} <a href="{{ route('pages.show', 'refunds') }}" class="link">{{ __('Refund policy') }}</a></p>
                    </div>
                </div>

                <div class="mt-8 space-y-4 text-sm leading-relaxed">
                    @if ($artwork->description)
                        <div>
                            <h2 class="font-semibold">{{ __('About the design') }}</h2>
                            <p class="mt-1 whitespace-pre-line text-muted">{{ $artwork->description }}</p>
                        </div>
                    @endif
                    <div>
                        <h2 class="font-semibold">{{ __('Product details') }}</h2>
                        <p class="mt-1 text-muted">{{ $product->translate('description') }}</p>
                    </div>
                    @if ($artwork->tags->isNotEmpty())
                        <div class="flex flex-wrap gap-2 pt-2">
                            @foreach ($artwork->tags as $tag)
                                <a href="{{ route('shop.index', ['q' => $tag->name]) }}" class="rounded-full bg-sand px-3 py-1 text-xs text-ink-soft hover:bg-line">#{{ $tag->name }}</a>
                            @endforeach
                        </div>
                    @endif
                    <p class="pt-2 text-xs text-subtle"><a href="{{ route('copyright.create', $artwork) }}" class="underline hover:text-ink">{{ __('Report this design') }}</a></p>
                </div>
            </div>
        </div>

        @if ($otherProducts->isNotEmpty())
            <section class="mt-20">
                <h2 class="mb-6 text-2xl font-bold">{{ __(':title is also available on', ['title' => $artwork->title]) }}</h2>
                <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-6">
                    @foreach ($otherProducts as $other)
                        <x-shop.product-card :item="$other" :show-artist="false" />
                    @endforeach
                </div>
            </section>
        @endif

        @if ($reviews->isNotEmpty())
            <section class="mt-20">
                <h2 class="mb-6 text-2xl font-bold">{{ __('Customer reviews') }} <span class="text-base font-normal text-muted">({{ number_format($reviews->avg('rating'), 1) }} / 5)</span></h2>
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($reviews as $review)
                        <article class="card p-5">
                            <p class="text-brand-700" aria-label="{{ __(':count out of 5 stars', ['count' => $review->rating]) }}">{{ str_repeat('★', $review->rating) }}<span class="text-line-strong">{{ str_repeat('★', 5 - $review->rating) }}</span></p>
                            @if ($review->title)<h3 class="mt-2 font-semibold">{{ $review->title }}</h3>@endif
                            @if ($review->body)<p class="mt-1 text-sm text-muted">{{ $review->body }}</p>@endif
                            <p class="mt-3 text-xs text-subtle">{{ $review->user->name }} · {{ $review->created_at->translatedFormat('j M Y') }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($related->isNotEmpty())
            <section class="mt-20">
                <h2 class="mb-6 text-2xl font-bold">{{ __('You might also like') }}</h2>
                <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-4">
                    @foreach ($related as $item)
                        <x-shop.product-card :item="$item" />
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-layouts.shop>
