<x-layouts.shop :title="__('Your cart')">
    <div class="container-page py-12">
        <h1 class="mb-8 text-3xl font-bold">{{ __('Your cart') }}</h1>
        @if ($summary->isEmpty())
            <x-ui.empty icon="cart" :title="__('Your cart is empty')" :description="__('Discover original designs from artists across Rwanda.')">
                <x-ui.button :href="route('shop.index')">{{ __('Start shopping') }}</x-ui.button>
            </x-ui.empty>
        @else
            <div class="grid gap-10 lg:grid-cols-[1fr_380px]">
                <ul class="divide-y divide-line rounded-2xl border border-line bg-white">
                    @foreach ($summary->lines as $line)
                        @php
                            $item = $line->item;
                            if ($item->isFinished()) {
                                $fv = $item->finishedVariant; $fp = $fv->product;
                                $href = route('finished.show', $fp); $name = $fp->title;
                                $detail = $fv->label() ?: __('Ready-made'); $by = $fp->artist->display_name;
                                $max = min(config('marketplace.cart_max_quantity'), max($fv->stock_quantity, 1));
                            } else {
                                $ap = $item->artistProduct;
                                $href = route('products.show', [$ap->artwork, $ap->product]); $name = $ap->artwork->title;
                                $detail = $ap->product->translate('name').' · '.$item->variant->color->name.($item->variant->size ? ' · '.$item->variant->size->label : '');
                                $by = $ap->artwork->artist->display_name;
                                $max = config('marketplace.cart_max_quantity');
                            }
                        @endphp
                        <li class="flex gap-4 p-4 sm:gap-6 sm:p-5">
                            <a href="{{ $href }}" class="aspect-square w-24 shrink-0 overflow-hidden rounded-xl bg-sand sm:w-28">
                                @if ($item->isFinished())
                                    <x-shop.finished-thumb :product="$fp" class="h-full w-full" />
                                @else
                                    <x-shop.mockup :product="$ap->product" :color="$item->variant->color" :version="$ap->artwork->currentVersion" :placements="$ap->placements" class="h-full w-full p-1.5" />
                                @endif
                            </a>
                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex justify-between gap-3">
                                    <div class="min-w-0">
                                        <a href="{{ $href }}" class="font-semibold hover:text-brand-700">{{ $name }}</a>
                                        <p class="text-sm text-muted">{{ $detail }}</p>
                                        <p class="text-xs text-subtle">{{ __('by :artist', ['artist' => $by]) }}@if ($item->isFinished()) · {{ __('Ready-made') }}@endif</p>
                                    </div>
                                    <p class="shrink-0 font-semibold">@if ($line->available)<x-ui.money :amount="$line->total()" :currency="$summary->currency" />@endif</p>
                                </div>
                                @unless ($line->available)
                                    <p class="mt-2 rounded-lg bg-danger-50 px-3 py-2 text-xs text-danger-700">{{ $line->problem }}</p>
                                @endunless
                                <div class="mt-auto flex items-center gap-3 pt-3">
                                    <form method="post" action="{{ route('cart.items.update', $item) }}" class="flex items-center gap-2">
                                        @csrf @method('patch')
                                        <label for="qty-{{ $item->id }}" class="sr-only">{{ __('Quantity') }}</label>
                                        <select id="qty-{{ $item->id }}" name="quantity" onchange="this.form.submit()" class="rounded-lg border border-line-strong bg-white py-1.5 pr-8 pl-3 text-sm">
                                            @for ($q = 1; $q <= max($max, $item->quantity); $q++)<option value="{{ $q }}" @selected($q === $item->quantity)>{{ $q }}</option>@endfor
                                        </select>
                                        <noscript><button class="text-xs underline">{{ __('Update') }}</button></noscript>
                                    </form>
                                    @if ($line->available)<span class="text-xs text-muted">× <x-ui.money :amount="$line->price->unitPrice" :currency="$summary->currency" /></span>@endif
                                    <form method="post" action="{{ route('cart.items.destroy', $item) }}" class="ml-auto">
                                        @csrf @method('delete')
                                        <button class="text-sm text-muted underline hover:text-danger-700">{{ __('Remove') }}</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                    <div class="card p-6">
                        <h2 class="text-lg font-semibold">{{ __('Order summary') }}</h2>
                        <dl class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between"><dt class="text-muted">{{ trans_choice(':count item|:count items', $summary->itemCount(), ['count' => $summary->itemCount()]) }}</dt><dd><x-ui.money :amount="$summary->subtotal" :currency="$summary->currency" /></dd></div>
                            @if ($summary->discount)
                                <div class="flex justify-between text-success-700"><dt>{{ __('Discount') }} ({{ $summary->discountCode->code }})</dt><dd>−<x-ui.money :amount="$summary->discount" :currency="$summary->currency" /></dd></div>
                            @endif
                            <div class="flex justify-between"><dt class="text-muted">{{ __('Delivery') }}</dt><dd class="text-muted">{{ __('At checkout') }}</dd></div>
                        </dl>
                        <div class="mt-5 flex justify-between border-t border-line pt-5 font-semibold">
                            <span>{{ __('Subtotal') }}</span>
                            <x-ui.money :amount="$summary->subtotal - $summary->discount" :currency="$summary->currency" />
                        </div>
                        <x-ui.button :href="route('checkout.show')" variant="accent" size="lg" class="mt-6 w-full {{ $summary->hasUnavailableItems() ? 'pointer-events-none opacity-50' : '' }}">{{ __('Checkout') }}</x-ui.button>
                        @if ($summary->hasUnavailableItems())<p class="mt-2 text-center text-xs text-danger-700">{{ __('Remove unavailable items to continue.') }}</p>@endif
                    </div>
                    <div class="card p-5">
                        @if ($cart?->discount_code)
                            <div class="flex items-center justify-between text-sm">
                                <span>{{ __('Code') }} <strong>{{ $cart->discount_code }}</strong></span>
                                <form method="post" action="{{ route('cart.discount.remove') }}">@csrf @method('delete')<button class="text-muted underline">{{ __('Remove') }}</button></form>
                            </div>
                            @if ($summary->discountError)<p class="mt-2 text-xs text-danger-700">{{ $summary->discountError }}</p>@endif
                        @else
                            <form method="post" action="{{ route('cart.discount.apply') }}" class="flex gap-2">
                                @csrf
                                <label for="discount_code" class="sr-only">{{ __('Discount code') }}</label>
                                <input id="discount_code" name="discount_code" value="{{ old('discount_code') }}" placeholder="{{ __('Discount code') }}" class="input uppercase">
                                <x-ui.button variant="secondary">{{ __('Apply') }}</x-ui.button>
                            </form>
                            @error('discount_code')<p class="mt-2 text-xs text-danger-700">{{ $message }}</p>@enderror
                        @endif
                    </div>
                </aside>
            </div>
        @endif
    </div>
</x-layouts.shop>
