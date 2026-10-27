<x-layouts.shop :title="__('Order :number', ['number' => $order->number])">
    <div class="container-page max-w-5xl py-12">
        @if ($order->status->value === 'cancelled')
            <div class="mb-6 rounded-xl border border-line bg-sand px-4 py-3 text-sm">{{ __('This order was cancelled.') }} @if ($order->cancel_reason)({{ $order->cancel_reason }})@endif</div>
        @endif

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="eyebrow">{{ __('Order') }}</p>
                <h1 class="mt-1 text-3xl font-bold">{{ $order->number }}</h1>
                <p class="mt-1 text-sm text-muted">{{ __('Placed :date', ['date' => $order->placed_at->translatedFormat('j F Y, H:i')]) }} · {{ $order->email }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.badge :status="$order->payment_status" />
                <x-ui.badge :status="$order->fulfillment_status" />
            </div>
        </div>

        {{-- Payment --}}
        @if ($order->isAwaitingPayment())
            <section class="mt-8 card overflow-hidden">
                @if ($pendingPayment)
                    <div class="flex flex-wrap items-center gap-5 bg-warning-100/60 p-6"
                        x-data="paymentPoller('{{ route('orders.payment-status', $order->tracking_token) }}', { status: 'pending' })">
                        <div class="grid size-12 place-items-center rounded-full bg-white text-warning-700"><x-ui.icon name="phone" class="size-6" /></div>
                        <div class="flex-1">
                            <h2 class="font-semibold">{{ __('Approve the payment on your phone') }}</h2>
                            <p class="text-sm text-muted">{{ __('We sent a :provider request for :amount to :phone. Enter your PIN to confirm. This page updates automatically.', [
                                'provider' => app(\App\Services\Payments\GatewayRegistry::class)->get($pendingPayment->provider)->label(),
                                'amount' => \App\Support\Money::format($pendingPayment->amount, $pendingPayment->currency),
                                'phone' => \App\Support\PhoneNumber::display($pendingPayment->payer_phone),
                            ]) }}</p>
                            <p class="mt-2 flex items-center gap-2 text-xs text-warning-700"><span class="size-2 animate-pulse rounded-full bg-warning-500"></span> {{ __('Waiting for confirmation…') }}</p>
                        </div>
                        @if (isset($pendingPayment->meta['redirect_url']))
                            <x-ui.button :href="$pendingPayment->meta['redirect_url']" variant="secondary" size="sm">{{ __('Open payment page') }}</x-ui.button>
                        @endif
                    </div>
                @endif
                <div class="p-6">
                    <h2 class="font-semibold">{{ $pendingPayment ? __('Didn’t get the prompt? Try again') : ($order->payment_status->value === 'failed' ? __('Payment was not completed') : __('Complete your payment')) }}</h2>
                    @php $lastFailed = $order->payments->firstWhere('status', \App\Enums\PaymentStatus::Failed) ?? $order->payments->firstWhere('status', \App\Enums\PaymentStatus::Expired); @endphp
                    @if (! $pendingPayment && $lastFailed?->failure_reason)<p class="mt-1 text-sm text-danger-700">{{ $lastFailed->failure_reason }}</p>@endif
                    <form method="post" action="{{ route('orders.pay', $order->tracking_token) }}" class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" x-data="{ method: '{{ $gateways[0]?->key() ?? '' }}' }">
                        @csrf
                        <x-form.field name="payment_method" :label="__('Method')">
                            <select name="payment_method" class="input" x-model="method">
                                @foreach ($gateways as $gateway)<option value="{{ $gateway->key() }}">{{ $gateway->label() }}</option>@endforeach
                            </select>
                        </x-form.field>
                        <x-form.input name="payer_phone" type="tel" :label="__('Mobile money number')" :value="\App\Support\PhoneNumber::display($order->phone)" x-bind:disabled="method === 'cash_on_delivery'" />
                        <x-ui.button variant="accent">{{ __('Pay :amount', ['amount' => \App\Support\Money::format($order->total_amount, $order->currency)]) }}</x-ui.button>
                    </form>
                </div>
            </section>
        @elseif ($order->payment_method === 'cash_on_delivery' && ! $order->isPaid() && $order->status->value !== 'cancelled')
            <p class="mt-8 rounded-xl bg-info-50 px-4 py-3 text-sm text-info-700">{{ __('Please have :amount ready in cash for the courier.', ['amount' => \App\Support\Money::format($order->total_amount, $order->currency)]) }}</p>
        @endif

        <section class="mt-8 card p-6">
            @include('shop.partials.order-timeline', ['order' => $order])
        </section>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_320px]">
            <div class="space-y-8">
                <section class="card">
                    <h2 class="border-b border-line px-6 py-4 font-semibold">{{ __('Items') }}</h2>
                    <ul class="divide-y divide-line">
                        @foreach ($order->items as $item)
                            <li class="flex gap-4 px-6 py-4">
                                <div class="aspect-square w-20 shrink-0 rounded-xl bg-sand">
                                    @if ($item->artistProduct)
                                        <x-shop.mockup :product="$item->artistProduct->product" :color="$item->variant->color" :version="$item->artworkVersion" :placements="collect($item->placement_snapshot)->map(fn ($p) => $p)->all()" class="h-full w-full p-1" />
                                    @elseif ($item->isFinished())
<x-shop.finished-thumb :product="$item->finishedProduct" class="h-full w-full rounded-xl" />
@endif
                                </div>
                                <div class="flex-1 text-sm">
                                    <p class="font-semibold">{{ $item->title }}</p>
                                    <p class="text-muted">{{ $item->product_name }} · {{ $item->variantLabel() }} · × {{ $item->quantity }}</p>
                                    <x-ui.badge :status="$item->fulfillment_status" class="mt-2" />
                                </div>
                                <p class="text-sm font-medium"><x-ui.money :amount="$item->line_total_amount" :currency="$order->currency" /></p>
                            </li>
                        @endforeach
                    </ul>
                </section>

                @if ($order->shipments->isNotEmpty())
                    <section class="card">
                        <h2 class="border-b border-line px-6 py-4 font-semibold">{{ __('Shipments') }}</h2>
                        @foreach ($order->shipments as $shipment)
                            <div class="border-b border-line px-6 py-5 last:border-b-0">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold">{{ $shipment->reference }} · {{ $shipment->carrier }}
                                        @if ($shipment->tracking_number)<span class="font-normal text-muted">#{{ $shipment->tracking_number }}</span>@endif</p>
                                    <x-ui.badge :status="$shipment->status" />
                                </div>
                                @if ($shipment->tracking_url)<a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener" class="link mt-1 inline-block text-xs">{{ __('Carrier tracking') }}</a>@endif
                                <ol class="mt-4 space-y-3 border-l border-line pl-5">
                                    @foreach ($shipment->events as $event)
                                        <li class="relative text-sm">
                                            <span class="absolute top-1.5 -left-[25px] size-2.5 rounded-full {{ $loop->first ? 'bg-success-600' : 'bg-line-strong' }}"></span>
                                            <p class="font-medium">{{ $event->status->label() }}@if ($event->location) · <span class="text-muted">{{ $event->location }}</span>@endif</p>
                                            <p class="text-xs text-muted">{{ $event->occurred_at->translatedFormat('j M Y, H:i') }}@if ($event->notes) — {{ $event->notes }}@endif</p>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endforeach
                    </section>
                @endif
            </div>

            <aside class="space-y-6">
                <section class="card p-5 text-sm">
                    <h2 class="font-semibold">{{ __('Summary') }}</h2>
                    <dl class="mt-3 space-y-2">
                        <div class="flex justify-between"><dt class="text-muted">{{ __('Subtotal') }}</dt><dd><x-ui.money :amount="$order->subtotal_amount" :currency="$order->currency" /></dd></div>
                        @if ($order->discount_amount)<div class="flex justify-between text-success-700"><dt>{{ __('Discount') }}</dt><dd>−<x-ui.money :amount="$order->discount_amount" :currency="$order->currency" /></dd></div>@endif
                        <div class="flex justify-between"><dt class="text-muted">{{ __('Delivery') }}</dt><dd><x-ui.money :amount="$order->delivery_amount" :currency="$order->currency" /></dd></div>
                        <div class="flex justify-between border-t border-line pt-2 font-semibold"><dt>{{ __('Total') }}</dt><dd><x-ui.money :amount="$order->total_amount" :currency="$order->currency" /></dd></div>
                        @if ($order->tax_amount)<p class="text-right text-xs text-muted">{{ __('Includes VAT of :amount', ['amount' => \App\Support\Money::format($order->tax_amount, $order->currency)]) }}</p>@endif
                        @if ($order->refunded_amount)<div class="flex justify-between text-brand-700"><dt>{{ __('Refunded') }}</dt><dd>−<x-ui.money :amount="$order->refunded_amount" :currency="$order->currency" /></dd></div>@endif
                    </dl>
                </section>
                <section class="card p-5 text-sm">
                    <h2 class="font-semibold">{{ __('Delivery to') }}</h2>
                    <p class="mt-2">{{ $order->address->recipient_name }}<br>{{ $order->address->summary() }}<br>{{ \App\Support\PhoneNumber::display($order->address->phone) }}</p>
                    <p class="mt-2 text-xs text-muted">{{ $order->deliveryZone->name }} · {{ $order->deliveryZone->estimateLabel() }}</p>
                </section>
                <section class="card p-5 text-sm">
                    <h2 class="font-semibold">{{ __('Need help?') }}</h2>
                    <p class="mt-1 text-muted">{{ __('Questions about delivery, a damaged item or a refund?') }}</p>
                    <a href="{{ route('support.create', ['order' => $order->number]) }}" class="link mt-2 inline-block">{{ __('Contact support') }}</a>
                    @if ($order->user_id && auth()->id() === $order->user_id)
                        <a href="{{ route('account.orders.show', $order) }}" class="link mt-2 block">{{ __('Request a refund from your account') }}</a>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-layouts.shop>
