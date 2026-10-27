@php
    use App\Enums\FulfillmentStatus;
    use App\Support\Money;
    $stepIndex = fn ($order) => match ($order->fulfillment_status) {
        FulfillmentStatus::Unfulfilled => 1,
        FulfillmentStatus::InProduction => 2,
        FulfillmentStatus::PartiallyShipped, FulfillmentStatus::Shipped, FulfillmentStatus::PartiallyDelivered => 3,
        FulfillmentStatus::Delivered => 4,
    };
    $steps = [__('Placed'), __('Paid'), __('Printing'), __('Shipped'), __('Delivered')];
@endphp
<x-layouts.dashboard :title="__('My account')" area="account">
    <x-dash.greeting :name="$user->name" :subtitle="__('Track your orders, manage addresses and keep your favourite designs in one place.')">
        <x-slot:actions>
            <x-ui.button :href="route('shop.index')" variant="accent" size="sm"><x-ui.icon name="store" class="size-4" /> {{ __('Continue shopping') }}</x-ui.button>
        </x-slot:actions>
    </x-dash.greeting>

    <x-dash.section step="1" :title="__('Overview')">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-dash.kpi :label="__('Orders')" :value="number_format($orderCount)" icon="receipt" tone="clay" :hint="trans_choice(':count on the way|:count on the way', $activeOrders->count(), ['count' => $activeOrders->count()])" :href="route('account.orders.index')" />
        <x-dash.kpi :label="__('Total spent')" :value="Money::format($totalSpent, 'RWF')" icon="wallet" tone="leaf" :hint="__('Paid orders, after refunds')" />
        <x-dash.kpi :label="__('Favourites')" :value="number_format($favoriteCount)" icon="heart" tone="rose" :href="route('account.favorites.index')" />
        <x-dash.kpi :label="__('Notifications')" :value="number_format($unreadCount)" icon="bell" :tone="$unreadCount ? 'gold' : 'neutral'" :hint="__('unread')" :href="route('account.notifications')" />
    </div>

    </x-dash.section>

    @if (array_sum($spending['totals']) > 0)
        <x-dash.section step="2" :title="__('Analytics')" :description="__('What you spent per month, after refunds')">
            <x-dash.panel :title="__('Monthly spending')" :description="__('Last six months, RWF')">
                <x-dash.chart :label="__('Monthly spending chart')" height="h-56" :config="[
                    'type' => 'bar',
                    'labels' => $spending['labels'],
                    'datasets' => [['label' => __('Spent'), 'data' => $spending['totals'], 'backgroundColor' => '--color-brand-600']],
                    'currency' => 'RWF',
                ]" />
            </x-dash.panel>
        </x-dash.section>
    @endif

    <x-dash.section :step="array_sum($spending['totals']) > 0 ? 3 : 2" :title="__('Order updates')" :description="__('Payments due and deliveries in progress')">
        <div class="space-y-3">
            @if ($awaitingPayment)
                <x-dash.alert tone="warning" icon="phone" :title="__('Order :number is waiting for payment of :amount.', ['number' => $awaitingPayment->number, 'amount' => Money::format($awaitingPayment->total_amount, $awaitingPayment->currency)])"
                    :href="route('orders.track', $awaitingPayment->tracking_token)" :action="__('Complete payment')">
                    {{ __('Production starts as soon as payment is confirmed.') }}
                </x-dash.alert>
            @endif
            @if ($activeOrders->isNotEmpty())
                <x-dash.panel :title="__('On the way')" :description="__('Orders being printed or delivered')">
                    @foreach ($activeOrders as $order)
                    @php $current = $stepIndex($order); @endphp
                    <a href="{{ route('account.orders.show', $order) }}" class="block rounded-xl border border-line p-4 transition hover:border-line-strong hover:bg-paper {{ $loop->last ? '' : 'mb-3' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-semibold">{{ $order->number }} <span class="font-normal text-muted">· {{ trans_choice(':count item|:count items', $order->items->sum('quantity'), ['count' => $order->items->sum('quantity')]) }}</span></p>
                            <x-ui.badge :status="$order->fulfillment_status" />
                        </div>
                        <ol class="mt-4 grid grid-cols-5 gap-1.5" aria-label="{{ __('Order progress') }}">
                            @foreach ($steps as $index => $label)
                                <li>
                                    <div @class(['h-1.5 rounded-full', 'bg-success-600' => $index <= $current, 'bg-line' => $index > $current])></div>
                                    <p @class(['mt-1.5 text-[11px] sm:text-xs', 'font-semibold text-ink' => $index === $current, 'text-muted' => $index !== $current])>{{ $label }}@if ($index <= $current)<span class="sr-only"> ({{ __('done') }})</span>@endif</p>
                                </li>
                            @endforeach
                        </ol>
                        @if ($shipment = $order->shipments->first())
                            <p class="mt-3 text-xs text-muted">{{ $shipment->carrier }} @if ($shipment->tracking_number)· #{{ $shipment->tracking_number }}@endif · {{ $shipment->status->label() }}</p>
                        @endif
                    </a>
                @endforeach
                </x-dash.panel>
            @elseif (! $awaitingPayment)
                <x-dash.alert tone="success" icon="check-circle" :title="__('Nothing on the way')" :href="route('shop.index')" :action="__('Browse designs')">
                    {{ __('Orders you place will be tracked here.') }}
                </x-dash.alert>
            @endif

        </div>
    </x-dash.section>

    <x-dash.section :step="array_sum($spending['totals']) > 0 ? 4 : 3" :title="__('Details')" :description="__('Orders, favourites and saved details')">
        <div class="grid items-start gap-4 sm:gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2">
            <x-dash.panel :title="__('Recent orders')" :href="route('account.orders.index')" :padding="false">
                @if ($recentOrders->isEmpty())
                    <p class="px-5 pb-5 text-sm text-muted">{{ __('No orders yet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Date') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Delivery') }}</th><th class="text-right">{{ __('Total') }}</th></tr></thead>
                            <tbody>
                                @foreach ($recentOrders as $order)
                                    <tr>
                                        <td><a href="{{ route('account.orders.show', $order) }}" class="font-mono text-xs font-medium hover:text-brand-700">{{ $order->number }}</a></td>
                                        <td class="whitespace-nowrap text-muted">{{ $order->placed_at->translatedFormat('j M Y') }}</td>
                                        <td><x-ui.badge :status="$order->payment_status" /></td>
                                        <td><x-ui.badge :status="$order->fulfillment_status" /></td>
                                        <td class="text-right font-medium tabular-nums">{{ Money::format($order->total_amount, $order->currency) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-dash.panel>
            </div>
            <div class="space-y-4 sm:space-y-6">
            <x-dash.panel :title="__('Favourites')" :href="route('account.favorites.index')">
                @if ($favorites->isEmpty())
                    <p class="text-sm text-muted">{{ __('Tap the heart on a product to save designs you love.') }}</p>
                @else
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($favorites as $artwork)
                            <a href="{{ route('designs.show', $artwork) }}" class="group">
                                <span class="block aspect-square overflow-hidden rounded-xl bg-sand p-2">
                                    @if ($artwork->currentVersion?->isReady())<img src="{{ $artwork->currentVersion->thumbnailUrl() }}" alt="" class="h-full w-full object-contain transition group-hover:scale-105">@endif
                                </span>
                                <span class="mt-1.5 block truncate text-xs font-medium">{{ $artwork->title }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-dash.panel>

            <x-dash.panel :title="__('Delivery address')" :href="route('account.addresses.index')" :link-label="__('Manage')">
                @if ($defaultAddress)
                    <p class="text-sm font-medium">{{ $defaultAddress->recipient_name }}</p>
                    <p class="text-sm text-muted">{{ $defaultAddress->summary() }}</p>
                    <p class="mt-1 text-xs text-subtle">{{ trans_choice(':count saved address|:count saved addresses', $addressCount, ['count' => $addressCount]) }}</p>
                @else
                    <p class="text-sm text-muted">{{ __('Save an address for faster checkout.') }}</p>
                    <x-ui.button :href="route('account.addresses.create')" variant="secondary" size="sm" class="mt-3">{{ __('Add address') }}</x-ui.button>
                @endif
            </x-dash.panel>

            @unless ($user->isArtist())
                <div class="relative overflow-hidden rounded-2xl bg-ink p-5 text-white">
                    <div class="absolute inset-x-0 top-0 imigongo-band-clay opacity-80"></div>
                    <p class="mt-2 font-display text-lg font-semibold">{{ __('Are you an artist?') }}</p>
                    <p class="mt-1 text-sm text-sand/75">{{ __('Open a free shop and earn on every product sold with your designs.') }}</p>
                    <a href="{{ route('account.become-artist') }}" class="mt-4 inline-flex items-center gap-2 rounded-full bg-brand-600 px-4 py-2 text-sm font-semibold text-ink hover:bg-brand-500">{{ __('Start selling') }} <x-ui.icon name="arrow-right" class="size-4" /></a>
                </div>
            @endunless
            </div>
        </div>
    </x-dash.section>
</x-layouts.dashboard>
