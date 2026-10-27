<x-layouts.dashboard :title="__('Order :number', ['number' => $order->number])" area="account">
    <div class="mb-4 text-sm"><a href="{{ route('account.orders.index') }}" class="text-muted hover:text-ink">← {{ __('All orders') }}</a></div>
    <x-ui.page-header :eyebrow="__('Placed :date', ['date' => $order->placed_at->translatedFormat('j F Y')])" :title="$order->number">
        <x-slot:actions>
            <x-ui.badge :status="$order->payment_status" />
            <x-ui.badge :status="$order->fulfillment_status" />
            <x-ui.button :href="route('orders.track', $order->tracking_token)" variant="secondary" size="sm">{{ $order->isAwaitingPayment() ? __('Complete payment') : __('Tracking page') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="card mb-6 p-6">@include('shop.partials.order-timeline', ['order' => $order])</div>

    <div class="grid gap-6 lg:grid-cols-[1fr_340px]">
        <div class="space-y-6">
            <x-ui.card :title="__('Items')" :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($order->items as $item)
                        <li class="px-5 py-4" x-data="{ reviewing: false }">
                            <div class="flex gap-4">
                                <div class="aspect-square w-20 shrink-0 rounded-xl bg-sand">
                                    @if ($item->artistProduct && $item->artworkVersion)
                                        <x-shop.mockup :product="$item->artistProduct->product" :color="$item->variant->color" :version="$item->artworkVersion" :placements="$item->placement_snapshot" class="h-full w-full p-1" />
                                    @elseif ($item->isFinished())
<x-shop.finished-thumb :product="$item->finishedProduct" class="h-full w-full rounded-xl" />
@endif
                                </div>
                                <div class="flex-1 text-sm">
                                    <p class="font-semibold">{{ $item->title }}</p>
                                    <p class="text-muted">{{ $item->product_name }} · {{ $item->variantLabel() }} · × {{ $item->quantity }}</p>
                                    <div class="mt-2 flex flex-wrap items-center gap-2">
                                        <x-ui.badge :status="$item->fulfillment_status" />
                                        @if ($item->refunded_amount)<x-ui.badge tone="accent">{{ __('Refunded :amount', ['amount' => \App\Support\Money::format($item->refunded_amount, $order->currency)]) }}</x-ui.badge>@endif
                                    </div>
                                </div>
                                <div class="text-right text-sm">
                                    <p class="font-medium"><x-ui.money :amount="$item->line_total_amount" :currency="$order->currency" /></p>
                                    @if ($item->fulfillment_status === \App\Enums\OrderItemStatus::Delivered && ! $item->review)
                                        <button type="button" class="link mt-2 text-xs" @click="reviewing = ! reviewing">{{ __('Write a review') }}</button>
                                    @elseif ($item->review)
                                        <p class="mt-2 text-xs text-brand-700" aria-label="{{ __(':count out of 5 stars', ['count' => $item->review->rating]) }}">{{ str_repeat('★', $item->review->rating) }}</p>
                                    @endif
                                </div>
                            </div>
                            @if ($item->fulfillment_status === \App\Enums\OrderItemStatus::Delivered && ! $item->review)
                                <form x-cloak x-show="reviewing" method="post" action="{{ route('account.orders.review', [$order, $item]) }}" class="mt-4 space-y-3 rounded-xl bg-paper p-4" x-data="{ rating: 5 }">
                                    @csrf
                                    <fieldset>
                                        <legend class="label">{{ __('Rating') }}</legend>
                                        <div class="flex gap-1">
                                            @for ($star = 1; $star <= 5; $star++)
                                                <label class="cursor-pointer text-2xl" :class="rating >= {{ $star }} ? 'text-brand-700' : 'text-line-strong'">
                                                    <input type="radio" name="rating" value="{{ $star }}" x-model.number="rating" class="sr-only"><span aria-hidden="true">★</span><span class="sr-only">{{ $star }}</span>
                                                </label>
                                            @endfor
                                        </div>
                                    </fieldset>
                                    <x-form.input name="title" :label="__('Title (optional)')" :id="'review-title-'.$item->id" />
                                    <x-form.textarea name="body" :label="__('Your review (optional)')" rows="3" :id="'review-body-'.$item->id" />
                                    <x-ui.button size="sm">{{ __('Publish review') }}</x-ui.button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            @if ($order->shipments->isNotEmpty())
                <x-ui.card :title="__('Shipments')">
                    <div class="space-y-6">
                        @foreach ($order->shipments as $shipment)
                            <div>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold">{{ $shipment->reference }} · {{ $shipment->carrier }}@if ($shipment->tracking_number) <span class="font-normal text-muted">#{{ $shipment->tracking_number }}</span>@endif</p>
                                    <x-ui.badge :status="$shipment->status" />
                                </div>
                                <p class="text-xs text-muted">{{ $shipment->items->map(fn ($l) => $l->orderItem->product_name.' × '.$l->quantity)->implode(', ') }}</p>
                                @if ($shipment->tracking_url)<a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener" class="link text-xs">{{ __('Carrier tracking') }}</a>@endif
                                <ol class="mt-3 space-y-2 border-l border-line pl-5">
                                    @foreach ($shipment->events as $event)
                                        <li class="relative text-sm">
                                            <span class="absolute top-1.5 -left-[25px] size-2.5 rounded-full {{ $loop->first ? 'bg-success-600' : 'bg-line-strong' }}"></span>
                                            <span class="font-medium">{{ $event->status->label() }}</span>
                                            <span class="text-xs text-muted">· {{ $event->occurred_at->translatedFormat('j M Y, H:i') }}@if ($event->location) · {{ $event->location }}@endif</span>
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif

            @if ($order->refunds->isNotEmpty())
                <x-ui.card :title="__('Refunds')" :padding="false">
                    <table class="table-base">
                        <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Amount') }}</th></tr></thead>
                        <tbody>
                            @foreach ($order->refunds as $refund)
                                <tr>
                                    <td><p class="font-medium">{{ $refund->reference }}</p><p class="text-xs text-muted">{{ $refund->created_at->translatedFormat('j M Y') }}</p></td>
                                    <td>{{ $refund->reason->label() }}@if ($refund->orderItem)<p class="text-xs text-muted">{{ $refund->orderItem->product_name }}</p>@endif</td>
                                    <td><x-ui.badge :status="$refund->status" />@if ($refund->admin_note && $refund->status->value === 'rejected')<p class="mt-1 text-xs text-muted">{{ $refund->admin_note }}</p>@endif</td>
                                    <td class="text-right"><x-ui.money :amount="$refund->amount" :currency="$refund->currency" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-ui.card>
            @endif

            @if ($canRequestRefund && $maxRefundable > 0)
                <x-ui.card :title="__('Request a refund')" :description="__('Damaged, misprinted or missing items? Tell us what happened. You can request up to :amount.', ['amount' => \App\Support\Money::format($maxRefundable, $order->currency)])">
                    <form method="post" action="{{ route('account.orders.refunds.store', $order) }}" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <x-form.select name="reason" :label="__('Reason')" :options="collect(\App\Enums\RefundReason::cases())->reject(fn ($r) => $r === \App\Enums\RefundReason::ProductionFailure)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()" required />
                        <x-form.select name="order_item_id" :label="__('Item')" :placeholder="__('Whole order')" :options="$order->items->mapWithKeys(fn ($i) => [$i->id => $i->product_name.' · '.$i->variantLabel().' × '.$i->quantity])->all()" />
                        <x-form.input name="amount" type="number" min="1" :max="$maxRefundable" :label="__('Amount (:currency)', ['currency' => $order->currency])" required />
                        <x-form.textarea name="customer_note" :label="__('What went wrong?')" rows="3" class="sm:col-span-2" required :hint="__('Photos help: reply to the confirmation e-mail or contact support after submitting.')" />
                        <div class="sm:col-span-2"><x-ui.button>{{ __('Submit refund request') }}</x-ui.button></div>
                    </form>
                </x-ui.card>
            @endif
        </div>

        <aside class="space-y-6">
            <x-ui.card :title="__('Summary')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Subtotal') }}</dt><dd><x-ui.money :amount="$order->subtotal_amount" :currency="$order->currency" /></dd></div>
                    @if ($order->discount_amount)<div class="flex justify-between text-success-700"><dt>{{ __('Discount') }}</dt><dd>−<x-ui.money :amount="$order->discount_amount" :currency="$order->currency" /></dd></div>@endif
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Delivery') }}</dt><dd><x-ui.money :amount="$order->delivery_amount" :currency="$order->currency" /></dd></div>
                    <div class="flex justify-between border-t border-line pt-2 font-semibold"><dt>{{ __('Total') }}</dt><dd><x-ui.money :amount="$order->total_amount" :currency="$order->currency" /></dd></div>
                    @if ($order->tax_amount)<p class="text-right text-xs text-muted">{{ __('Includes VAT of :amount', ['amount' => \App\Support\Money::format($order->tax_amount, $order->currency)]) }}</p>@endif
                    @if ($order->refunded_amount)<div class="flex justify-between text-brand-700"><dt>{{ __('Refunded') }}</dt><dd>−<x-ui.money :amount="$order->refunded_amount" :currency="$order->currency" /></dd></div>@endif
                </dl>
                @php $paid = $order->payments->firstWhere('status', \App\Enums\PaymentStatus::Succeeded); @endphp
                @if ($paid)
                    <p class="mt-4 text-xs text-muted">{{ __('Paid with :method on :date', ['method' => app(\App\Services\Payments\GatewayRegistry::class)->get($paid->provider)->label(), 'date' => $paid->confirmed_at?->translatedFormat('j M Y')]) }}</p>
                @endif
            </x-ui.card>
            <x-ui.card :title="__('Delivery address')">
                <p class="text-sm">{{ $order->address->recipient_name }}<br>{{ $order->address->summary() }}<br>{{ \App\Support\PhoneNumber::display($order->address->phone) }}</p>
                <p class="mt-2 text-xs text-muted">{{ $order->deliveryZone->name }} · {{ $order->deliveryZone->estimateLabel() }}</p>
            </x-ui.card>
            <x-ui.card :title="__('Need help?')">
                <a href="{{ route('support.create', ['order' => $order->number]) }}" class="link text-sm">{{ __('Contact support about this order') }}</a>
            </x-ui.card>
        </aside>
    </div>
</x-layouts.dashboard>
