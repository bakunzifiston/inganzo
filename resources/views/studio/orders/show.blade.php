<x-layouts.dashboard :title="__('Order :number', ['number' => $order->number])" area="studio">
    <div class="mb-6 text-sm text-muted"><a href="{{ route('studio.orders.index') }}" class="hover:text-ink">{{ __('Orders') }}</a> / {{ $order->number }}</div>
    <x-ui.page-header :title="__('Order :number', ['number' => $order->number])" :description="__('Placed :date', ['date' => $order->placed_at->translatedFormat('j M Y, H:i')])">
        <x-slot:actions><x-ui.badge :status="$order->payment_status" /></x-slot:actions>
    </x-ui.page-header>

    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="space-y-6">
            <x-ui.card :title="__('Your items')" :padding="false">
                <form method="post" action="{{ route('studio.orders.ship', $order) }}" id="ship-form">@csrf</form>
                <ul class="divide-y divide-line">
                    @foreach ($items as $item)
                        <li class="flex flex-wrap items-start gap-4 p-5">
                            @if ($canShip && $item->isFinished() && $item->fulfillment_status === \App\Enums\OrderItemStatus::AwaitingShipment)
                                <input type="checkbox" form="ship-form" name="items[]" value="{{ $item->id }}" checked class="mt-1.5 size-4 accent-brand-600" aria-label="{{ __('Include in shipment') }}">
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2"><p class="font-semibold">{{ $item->title }}</p><x-ui.badge :status="$item->selling_type" /></div>
                                <p class="text-sm text-muted">{{ $item->variantLabel() ?: __('No options') }} · {{ __('SKU') }} <span class="font-mono text-xs">{{ $item->sku }}</span></p>
                                <p class="mt-1 text-sm">{{ $item->quantity }} × <x-ui.money :amount="$item->unit_price_amount" :currency="$order->currency" /></p>
                            </div>
                            <div class="text-right">
                                <x-ui.badge :status="$item->fulfillment_status" />
                                <p class="mt-1 text-sm font-medium">{{ __('You earn') }} <x-ui.money :amount="$item->artistEarningTotal()" :currency="$order->currency" /></p>
                                @if ($item->refunded_amount)<p class="text-xs text-danger-700">{{ __('Refunded') }} <x-ui.money :amount="$item->refunded_amount" :currency="$order->currency" /></p>@endif
                            </div>
                            @if ($item->isFinished() && $item->fulfillment_status === \App\Enums\OrderItemStatus::AwaitingShipment)
                                <details class="w-full text-sm">
                                    <summary class="cursor-pointer text-xs text-muted underline">{{ __('I cannot fulfil this item') }}</summary>
                                    <form method="post" action="{{ route('studio.orders.cannot-fulfil', [$order, $item]) }}" class="mt-2 space-y-2" onsubmit="return confirm('{{ __('Request a refund for this item?') }}')">
                                        @csrf
                                        <textarea name="note" rows="2" required minlength="5" maxlength="500" class="input" placeholder="{{ __('Tell us why (for example, the item is damaged or sold elsewhere).') }}"></textarea>
                                        <x-ui.button variant="secondary" size="sm">{{ __('Request refund') }}</x-ui.button>
                                    </form>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if ($canShip)
                    <div class="border-t border-line bg-paper p-5">
                        <h3 class="font-semibold">{{ __('Ship selected items') }}</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <label><span class="studio-label">{{ __('Carrier') }}</span><input form="ship-form" name="carrier" required maxlength="80" class="input" value="{{ old('carrier') }}" placeholder="{{ __('e.g. Volcano Express') }}"></label>
                            <label><span class="studio-label">{{ __('Tracking number') }}</span><input form="ship-form" name="tracking_number" maxlength="80" class="input" value="{{ old('tracking_number') }}"></label>
                            <label><span class="studio-label">{{ __('Tracking link') }}</span><input form="ship-form" name="tracking_url" type="url" maxlength="255" class="input" value="{{ old('tracking_url') }}"></label>
                        </div>
                        @if ($errors->any())<ul class="mt-3 space-y-1 text-xs text-danger-700">@foreach ($errors->all() as $message)<li>• {{ $message }}</li>@endforeach</ul>@endif
                        <x-ui.button form="ship-form" variant="accent" class="mt-4"><x-ui.icon name="truck" class="size-4" /> {{ __('Mark as shipped') }}</x-ui.button>
                    </div>
                @endif
            </x-ui.card>

            @if ($shipments->isNotEmpty())
                <x-ui.card :title="__('Shipments')">
                    <ul class="space-y-4">
                        @foreach ($shipments as $shipment)
                            <li class="rounded-xl border border-line p-4 text-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-mono text-xs font-medium">{{ $shipment->reference }}</p>
                                    <x-ui.badge :status="$shipment->status" />
                                </div>
                                <p class="mt-1 text-muted">{{ $shipment->carrier }}@if ($shipment->tracking_number) · {{ $shipment->tracking_number }}@endif @if ($shipment->tracking_url)· <a href="{{ $shipment->tracking_url }}" target="_blank" rel="noopener" class="link">{{ __('Track') }}</a>@endif</p>
                                @if (! in_array($shipment->status, [\App\Enums\ShipmentStatus::Delivered, \App\Enums\ShipmentStatus::Returned], true))
                                    <form method="post" action="{{ route('studio.orders.delivered', [$order, $shipment]) }}" class="mt-3 flex flex-wrap items-center gap-3">
                                        @csrf
                                        @if ($order->payment_method === 'cash_on_delivery' && ! $order->isPaid())
                                            <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" name="cash_collected" value="1" class="accent-brand-600"> {{ __('Cash was collected') }}</label>
                                        @endif
                                        <x-ui.button variant="secondary" size="sm">{{ __('Mark as delivered') }}</x-ui.button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <aside class="space-y-6">
            @if ($showAddress)
                <x-ui.card :title="__('Deliver to')">
                    <address class="space-y-0.5 text-sm not-italic">
                        <p class="font-semibold">{{ $order->address->recipient_name }}</p>
                        <p><a href="tel:{{ $order->address->phone }}" class="link">{{ $order->address->phone }}</a></p>
                        <p>{{ $order->address->street_address }}</p>
                        <p>{{ collect([$order->address->landmark, $order->address->sector, $order->address->district, $order->address->country?->name])->filter()->implode(', ') }}</p>
                    </address>
                    @if ($order->customer_note)<p class="mt-3 rounded-lg bg-sand p-3 text-xs text-muted">{{ $order->customer_note }}</p>@endif
                    <p class="mt-3 text-xs text-subtle">{{ __('Use these details only to deliver this order.') }}</p>
                </x-ui.card>
            @endif
            <x-ui.card :title="__('Payment')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Method') }}</dt><dd>{{ str($order->payment_method)->replace('_', ' ')->title() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Status') }}</dt><dd><x-ui.badge :status="$order->payment_status" /></dd></div>
                    <div class="flex justify-between border-t border-line pt-2 font-semibold"><dt>{{ __('Your earnings') }}</dt><dd><x-ui.money :amount="$items->sum(fn ($i) => $i->artistEarningTotal())" :currency="$order->currency" /></dd></div>
                </dl>
                @if ($order->payment_method === 'cash_on_delivery' && ! $order->isPaid())<p class="mt-3 text-xs text-muted">{{ __('Cash on delivery: collect the payment when you hand over the order.') }}</p>@endif
            </x-ui.card>
        </aside>
    </div>
</x-layouts.dashboard>
