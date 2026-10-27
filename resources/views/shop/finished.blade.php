@php
    $images = $product->images;
    $currency = $product->currency;
    $shareUrl = route('finished.show', $product);
@endphp
<x-layouts.shop :title="$product->title" :description="\Illuminate\Support\Str::limit($product->description ?? __('Ready-made by :artist', ['artist' => $product->artist->display_name]), 150)">
    <div class="container-page py-8 lg:py-12"
        x-data="{
            variants: @js($variants),
            color: @js($variants->first()['color']),
            size: null,
            qty: 1,
            max: {{ $maxQuantity }},
            image: 0,
            init() { this.pickSize(); },
            colors() { return [...new Map(this.variants.filter(v => v.color).map(v => [v.color, v.hex])).entries()]; },
            sizesFor() { return this.variants.filter(v => v.color === this.color && v.size); },
            pickSize() {
                const sizes = this.sizesFor();
                const inStock = sizes.find(s => s.stock > 0) ?? sizes[0];
                if (! sizes.some(s => s.size === this.size)) this.size = inStock ? inStock.size : null;
                this.clamp();
            },
            get variant() { return this.variants.find(v => v.color === this.color && v.size === this.size) ?? null; },
            get stock() { return this.variant ? this.variant.stock : 0; },
            clamp() { this.qty = Math.max(1, Math.min(this.qty, Math.max(1, Math.min(this.max, this.stock)))); },
            money(n) { return '{{ $currency }} ' + Number(n).toLocaleString('en-US'); },
        }" x-effect="clamp()">
        <nav class="mb-6 text-xs text-muted" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('home') }}" class="hover:text-ink">{{ __('Home') }}</a> /
            <a href="{{ route('shop.index', ['type' => \App\Services\Catalog\CatalogSearch::READY_MADE]) }}" class="hover:text-ink">{{ __('Ready-made') }}</a> /
            <span class="text-ink">{{ $product->title }}</span>
        </nav>

        @unless ($listed)
            <div class="mb-6 rounded-xl border border-warning-100 bg-warning-50 p-4 text-sm text-warning-700">{{ __('Preview: this product is not visible to customers yet (:status).', ['status' => mb_strtolower($product->status->label())]) }}</div>
        @endunless

        <div class="grid gap-10 lg:grid-cols-[1.15fr_1fr] lg:gap-14">
            {{-- Gallery --}}
            <div>
                <div class="relative aspect-square overflow-hidden rounded-3xl bg-sand">
                    @forelse ($images as $i => $image)
                        <img x-show="image === {{ $i }}" @if ($i > 0) x-cloak @endif src="{{ $image->previewUrl() }}" alt="{{ $product->title }} — {{ __('photo :n', ['n' => $i + 1]) }}" class="absolute inset-0 h-full w-full object-cover">
                    @empty
                        <div class="grid h-full place-items-center text-sm text-muted">{{ __('No photo yet') }}</div>
                    @endforelse
                </div>
                @if ($images->count() > 1)
                    <div class="mt-4 flex flex-wrap gap-3">
                        @foreach ($images as $i => $image)
                            <button type="button" @click="image = {{ $i }}" :class="image === {{ $i }} ? 'ring-2 ring-ink' : 'ring-1 ring-line'" class="aspect-square w-20 overflow-hidden rounded-xl bg-sand" aria-label="{{ __('Show photo :n', ['n' => $i + 1]) }}">
                                <img src="{{ $image->thumbnailUrl() }}" alt="" class="h-full w-full object-cover">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Purchase panel --}}
            <div class="lg:pt-2">
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('artists.show', $product->artist) }}" class="inline-flex items-center gap-2 text-sm font-medium text-muted hover:text-brand-700">
                        <x-shop.avatar :artist="$product->artist" class="size-7 text-xs" /> {{ $product->artist->display_name }}
                    </a>
                    @if ($product->is_handmade)<x-ui.badge tone="accent">{{ __('Handmade') }}</x-ui.badge>@endif
                </div>
                <h1 class="mt-3 text-3xl font-bold sm:text-4xl">{{ $product->title }}</h1>

                <p class="mt-5 flex flex-wrap items-baseline gap-x-3 font-display text-3xl font-semibold">
                    <span x-text="variant ? money(variant.price) : ''"></span>
                    <span x-show="variant && variant.regular > variant.price" x-cloak class="text-lg font-normal text-subtle line-through" x-text="variant ? money(variant.regular) : ''"></span>
                    <noscript><x-ui.money :amount="$product->price_from_amount" :currency="$currency" /></noscript>
                </p>
                <p class="mt-1 text-xs text-muted">{{ __('Price includes VAT. Delivery calculated at checkout.') }}</p>

                {{-- Availability --}}
                <p class="mt-4 text-sm font-medium" role="status" aria-live="polite">
                    <template x-if="variant && stock > {{ $product->low_stock_threshold }}"><span class="text-success-700">● {{ __('In stock') }}</span></template>
                    <template x-if="variant && stock > 0 && stock <= {{ $product->low_stock_threshold }}"><span class="text-warning-700" x-text="`● ${stock === 1 ? '{{ __('Only 1 left') }}' : '{{ __('Only') }} ' + stock + ' {{ __('left') }}'}`"></span></template>
                    <template x-if="! variant || stock < 1"><span class="text-danger-700">● {{ __('Out of stock') }}</span></template>
                </p>

                <form method="post" action="{{ route('cart.items.store') }}" class="mt-6 space-y-6">
                    @csrf
                    <input type="hidden" name="finished_variant_id" :value="variant?.id">

                    <template x-if="colors().length">
                        <fieldset>
                            <legend class="text-sm font-semibold">{{ __('Colour') }}: <span class="font-normal text-muted" x-text="color"></span></legend>
                            <div class="mt-3 flex flex-wrap gap-2.5">
                                <template x-for="[name, hex] in colors()" :key="name">
                                    <button type="button" @click="color = name; pickSize()" :class="color === name ? 'ring-2 ring-ink ring-offset-2' : 'ring-1 ring-line-strong'"
                                        class="size-10 rounded-full transition" :style="`background: ${hex}`" :aria-label="name" :aria-pressed="color === name"></button>
                                </template>
                            </div>
                        </fieldset>
                    </template>

                    <template x-if="sizesFor().length">
                        <fieldset>
                            <legend class="text-sm font-semibold">{{ __('Size') }}</legend>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <template x-for="option in sizesFor()" :key="option.size">
                                    <button type="button" @click="size = option.size; clamp()" x-text="option.size" :disabled="option.stock < 1"
                                        :class="[size === option.size ? 'border-brand-600 bg-brand-600 text-ink' : 'border-line-strong bg-white hover:border-ink', option.stock < 1 ? 'cursor-not-allowed text-subtle line-through opacity-60' : '']"
                                        class="min-w-14 rounded-xl border px-4 py-2.5 text-sm font-semibold transition"></button>
                                </template>
                            </div>
                        </fieldset>
                    </template>

                    <div class="flex items-end gap-3">
                        <div class="w-32">
                            <label for="quantity" class="text-sm font-semibold">{{ __('Quantity') }}</label>
                            <div class="mt-2 flex items-center rounded-full border border-line-strong bg-white">
                                <button type="button" class="p-3" @click="qty = Math.max(1, qty - 1)" aria-label="{{ __('Decrease') }}"><x-ui.icon name="minus" class="size-4" /></button>
                                <input id="quantity" name="quantity" type="number" min="1" :max="Math.max(1, Math.min(max, stock))" x-model.number="qty" @change="clamp()" class="w-full border-0 bg-transparent p-0 text-center text-sm font-semibold focus:ring-0">
                                <button type="button" class="p-3" @click="qty = Math.min(Math.max(1, Math.min(max, stock)), qty + 1)" aria-label="{{ __('Increase') }}"><x-ui.icon name="plus" class="size-4" /></button>
                            </div>
                        </div>
                        <x-ui.button variant="accent" size="lg" class="flex-1" x-bind:disabled="! variant || stock < 1 || {{ $listed ? 'false' : 'true' }}">
                            <x-ui.icon name="cart" class="size-5" /> <span x-text="variant && stock > 0 ? '{{ __('Add to cart') }}' : '{{ __('Out of stock') }}'"></span>
                        </x-ui.button>
                    </div>
                    @error('variant')<p class="text-sm text-danger-700">{{ $message }}</p>@enderror
                </form>

                <div class="mt-8 divide-y divide-line rounded-2xl border border-line bg-white">
                    <div class="flex gap-3 p-4">
                        <x-ui.icon name="package" class="mt-0.5 size-5 shrink-0 text-success-600" />
                        <div class="text-sm">
                            <p class="font-semibold">{{ $product->is_handmade ? __('Handmade and shipped by :artist', ['artist' => $product->artist->display_name]) : __('Shipped by :artist', ['artist' => $product->artist->display_name]) }}</p>
                            <p class="text-muted">{{ trans_choice('Usually ready to ship in :count business day.|Usually ready to ship in :count business days.', max(1, $product->processing_days), ['count' => max(1, $product->processing_days)]) }}@if ($product->ships_from) · {{ __('Ships from :place', ['place' => $product->ships_from]) }}@endif</p>
                            @if ($product->shipping_note)<p class="mt-1 text-muted">{{ $product->shipping_note }}</p>@endif
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
                        <p class="text-sm text-muted">{{ __('Damaged or not as described? Contact us for a refund.') }} <a href="{{ route('pages.show', 'refunds') }}" class="link">{{ __('Refund policy') }}</a></p>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-2 text-sm" x-data="{ copied: false }">
                    <a href="https://wa.me/?text={{ urlencode($product->title.' — '.$shareUrl) }}" target="_blank" rel="noopener" class="rounded-full p-2 text-muted hover:bg-sand hover:text-ink" aria-label="{{ __('Share on WhatsApp') }}"><x-ui.icon name="share" class="size-4" /></a>
                    <button type="button" class="text-xs font-medium text-muted hover:text-ink" @click="navigator.clipboard.writeText('{{ $shareUrl }}'); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-show="! copied">{{ __('Copy link') }}</span><span x-cloak x-show="copied">{{ __('Copied!') }}</span>
                    </button>
                </div>

                <div class="mt-8 space-y-4 text-sm leading-relaxed">
                    @if ($product->description)
                        <div><h2 class="font-semibold">{{ __('About this product') }}</h2><p class="mt-1 whitespace-pre-line text-muted">{{ $product->description }}</p></div>
                    @endif
                    <dl class="grid grid-cols-2 gap-y-1.5 text-xs">
                        @if ($product->category)<dt class="text-muted">{{ __('Category') }}</dt><dd>{{ $product->category->translate('name') }}</dd>@endif
                        @if ($product->weight_grams)<dt class="text-muted">{{ __('Weight') }}</dt><dd>{{ number_format($product->weight_grams) }} g</dd>@endif
                        <dt class="text-muted">{{ __('SKU') }}</dt><dd class="font-mono" x-text="variant ? variant.sku : '{{ $product->sku ?: '—' }}'"></dd>
                    </dl>
                </div>
            </div>
        </div>

        @foreach ([[__('More from :artist', ['artist' => $product->artist->display_name]), $moreFromArtist], [__('You might also like'), $related]] as [$heading, $set])
            @if ($set->isNotEmpty())
                <section class="mt-20">
                    <h2 class="mb-6 text-2xl font-bold">{{ $heading }}</h2>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-4">
                        @foreach ($set as $item)<x-shop.product-card :item="$item" />@endforeach
                    </div>
                </section>
            @endif
        @endforeach
    </div>
</x-layouts.shop>
