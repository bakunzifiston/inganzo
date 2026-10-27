@php
    $user = auth()->user();
    $districtZones = $districts->flatten()->mapWithKeys(fn ($d) => [$d->id => ['fee' => $d->deliveryZone->fee_amount, 'free' => $d->deliveryZone->free_over_amount, 'eta' => $d->deliveryZone->estimateLabel(), 'zone' => $d->deliveryZone->name]]);
    $afterDiscount = $summary->subtotal - $summary->discount;
@endphp
<x-layouts.shop :title="__('Checkout')">
    <div class="container-page py-12"
        x-data="{
            district: @js((string) old('district_id', $selectedDistrict?->id ?? '')),
            method: @js(old('payment_method', $gateways[0]?->key() ?? '')),
            zones: @js($districtZones),
            base: {{ $afterDiscount }},
            phone: @js(old('phone', $savedAddress?->phone ?? $user?->phone ?? '')),
            payer: @js(old('payer_phone', '')),
            get zone() { return this.zones[this.district] ?? null },
            get delivery() { if (! this.zone) return null; return this.zone.free !== null && this.base >= this.zone.free ? 0 : this.zone.fee },
            get total() { return this.base + (this.delivery ?? 0) },
            money(v) { return '{{ $summary->currency }} ' + Number(v).toLocaleString('en-US') },
        }">
        <div class="mb-8 flex items-center gap-3 text-sm text-muted">
            <a href="{{ route('cart.show') }}" class="hover:text-ink">{{ __('Cart') }}</a>
            <x-ui.icon name="chevron-right" class="size-4" />
            <span class="font-semibold text-ink">{{ __('Delivery & payment') }}</span>
            <x-ui.icon name="chevron-right" class="size-4" />
            <span>{{ __('Confirmation') }}</span>
        </div>

        <form method="post" action="{{ route('checkout.store') }}" class="grid gap-10 lg:grid-cols-[1fr_400px]">
            @csrf
            <div class="space-y-8">
                @if ($errors->has('cart'))
                    <div class="rounded-xl border border-danger-700/20 bg-danger-50 px-4 py-3 text-sm text-danger-700">{{ $errors->first('cart') }} <a href="{{ route('cart.show') }}" class="underline">{{ __('Review cart') }}</a></div>
                @endif

                <section class="card p-6">
                    <h2 class="text-lg font-semibold">{{ __('Contact') }}</h2>
                    @guest
                        <p class="mt-1 text-sm text-muted">{{ __('Checking out as a guest.') }} <a href="{{ route('login') }}" class="link">{{ __('Sign in') }}</a> {{ __('to save your order history.') }}</p>
                    @endguest
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <x-form.input name="name" :label="__('Full name')" :value="$savedAddress?->recipient_name ?? $user?->name" autocomplete="name" required />
                        <x-form.input name="email" type="email" :label="__('E-mail')" :value="$user?->email" autocomplete="email" required :hint="__('We send your receipt and tracking link here.')" />
                        <x-form.input name="phone" type="tel" :label="__('Phone number')" x-model="phone" autocomplete="tel" required placeholder="078 123 4567" :hint="__('The courier calls this number on delivery.')" />
                    </div>
                </section>

                <section class="card p-6">
                    <h2 class="text-lg font-semibold">{{ __('Delivery address') }}</h2>
                    <p class="mt-1 text-sm text-muted">{{ __('We currently deliver within :country.', ['country' => $country->name]) }}</p>
                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <x-form.field name="district_id" :label="__('District')" required>
                            <select name="district_id" id="district_id" x-model="district" required @class(['input', 'input-error' => $errors->has('district_id')])>
                                <option value="">{{ __('Choose a district') }}</option>
                                @foreach ($districts as $province => $list)
                                    <optgroup label="{{ $province }}">
                                        @foreach ($list as $district)<option value="{{ $district->id }}">{{ $district->name }}</option>@endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </x-form.field>
                        <x-form.input name="sector" :label="__('Sector')" :value="$savedAddress?->sector" placeholder="Kimironko" />
                        <x-form.input name="street_address" :label="__('Street address or house number')" :value="$savedAddress?->street_address" class="sm:col-span-2" required placeholder="KG 11 Ave, house 24" />
                        <x-form.input name="landmark" :label="__('Landmark (optional)')" :value="$savedAddress?->landmark" class="sm:col-span-2" :placeholder="__('Near the market, blue gate…')" />
                        <x-form.textarea name="note" :label="__('Note for the courier (optional)')" rows="2" class="sm:col-span-2" />
                    </div>
                    <template x-if="zone">
                        <p class="mt-4 flex items-center gap-2 rounded-lg bg-success-50 px-3 py-2 text-sm text-success-700">
                            <x-ui.icon name="truck" class="size-4" />
                            <span x-text="zone.zone + ' · ' + zone.eta + ' {{ __('after production') }}'"></span>
                        </p>
                    </template>
                    @auth
                        <x-form.checkbox name="save_address" :label="__('Save this address to my account')" class="mt-4" :checked="! $savedAddress" />
                    @endauth
                </section>

                <section class="card p-6">
                    <h2 class="text-lg font-semibold">{{ __('Payment') }}</h2>
                    @if ($gateways === [])
                        <p class="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-sm text-warning-700">{{ __('Online payment is temporarily unavailable. Please contact support to complete your order.') }}</p>
                    @endif
                    <div class="mt-5 space-y-3">
                        @foreach ($gateways as $gateway)
                            <label class="flex cursor-pointer items-start gap-4 rounded-xl border p-4 transition" :class="method === '{{ $gateway->key() }}' ? 'border-ink bg-paper' : 'border-line hover:border-line-strong'">
                                <input type="radio" name="payment_method" value="{{ $gateway->key() }}" x-model="method" class="mt-1 accent-brand-600">
                                <span class="flex-1">
                                    <span class="flex flex-wrap items-center gap-2 font-semibold">
                                        {{ $gateway->label() }}
                                        @unless ($gateway->isLive())<x-ui.badge tone="warning">{{ __('Test mode — no real money') }}</x-ui.badge>@endunless
                                    </span>
                                    <span class="mt-0.5 block text-sm text-muted">{{ $gateway->description() }}</span>
                                </span>
                            </label>
                        @endforeach
                        @error('payment_method')<p class="text-xs font-medium text-danger-700">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="['mtn_momo', 'airtel_money', 'simulator'].includes(method)" class="mt-5 max-w-sm">
                        <x-form.input name="payer_phone" type="tel" :label="__('Mobile money number')" x-model="payer" placeholder="078 123 4567" :hint="__('You will receive a prompt on this phone to approve the payment.')" />
                        <button type="button" class="mt-1 text-xs text-brand-700 underline" @click="payer = phone">{{ __('Use my contact number') }}</button>
                    </div>
                </section>
            </div>

            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="card p-6">
                    <h2 class="text-lg font-semibold">{{ __('Your order') }}</h2>
                    <ul class="mt-5 space-y-4">
                        @foreach ($summary->lines as $line)
                            @php $ap = $line->item->artistProduct; $fv = $line->item->finishedVariant; @endphp
                            <li class="flex gap-3">
                                <div class="relative aspect-square w-16 shrink-0 rounded-lg bg-sand">
                                    @if ($fv)
                                        <x-shop.finished-thumb :product="$fv->product" class="h-full w-full rounded-lg" />
                                    @else
                                        <x-shop.mockup :product="$ap->product" :color="$line->item->variant->color" :version="$ap->artwork->currentVersion" :placements="$ap->placements" class="h-full w-full p-1" />
                                    @endif
                                    <span class="absolute -top-2 -right-2 grid size-5 place-items-center rounded-full bg-ink text-[10px] font-bold text-white">{{ $line->item->quantity }}</span>
                                </div>
                                <div class="min-w-0 flex-1 text-sm">
                                    @if ($fv)
                                        <p class="truncate font-medium">{{ $fv->product->title }}</p>
                                        <p class="truncate text-xs text-muted">{{ $fv->label() ?: __('Ready-made') }}</p>
                                    @else
                                        <p class="truncate font-medium">{{ $ap->artwork->title }}</p>
                                        <p class="truncate text-xs text-muted">{{ $ap->product->translate('name') }} · {{ $line->item->variant->color->name }}@if ($line->item->variant->size) · {{ $line->item->variant->size->label }}@endif</p>
                                    @endif
                                </div>
                                <p class="text-sm font-medium"><x-ui.money :amount="$line->total()" :currency="$summary->currency" /></p>
                            </li>
                        @endforeach
                    </ul>
                    <dl class="mt-6 space-y-2.5 border-t border-line pt-5 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">{{ __('Subtotal') }}</dt><dd><x-ui.money :amount="$summary->subtotal" :currency="$summary->currency" /></dd></div>
                        @if ($summary->discount)
                            <div class="flex justify-between text-success-700"><dt>{{ __('Discount') }}</dt><dd>−<x-ui.money :amount="$summary->discount" :currency="$summary->currency" /></dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-muted">{{ __('Delivery') }}</dt><dd x-text="delivery === null ? '{{ __('Choose a district') }}' : (delivery === 0 ? '{{ __('Free') }}' : money(delivery))"></dd></div>
                    </dl>
                    <div class="mt-5 flex items-baseline justify-between border-t border-line pt-5">
                        <span class="font-semibold">{{ __('Total') }}</span>
                        <span class="font-display text-2xl font-semibold" x-text="money(total)"></span>
                    </div>
                    @if ($summary->taxRateBps)<p class="mt-1 text-right text-xs text-muted">{{ __('Includes :rate% VAT', ['rate' => $summary->taxRateBps / 100]) }}</p>@endif
                    <x-ui.button variant="accent" size="lg" class="mt-6 w-full" :disabled="$gateways === []">{{ __('Place order') }}</x-ui.button>
                    <p class="mt-3 text-center text-xs text-muted">{{ __('By placing your order you agree to our') }} <a href="{{ route('pages.show', 'terms') }}" class="underline">{{ __('terms') }}</a> {{ __('and') }} <a href="{{ route('pages.show', 'refunds') }}" class="underline">{{ __('refund policy') }}</a>.</p>
                </div>
            </aside>
        </form>
    </div>
</x-layouts.shop>
