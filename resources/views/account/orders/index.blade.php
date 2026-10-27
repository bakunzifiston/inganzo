<x-layouts.dashboard :title="__('Orders')" area="account">
    <x-ui.page-header :title="__('Your orders')" :description="__('Every order you placed while signed in.')" />
    @if ($orders->isEmpty())
        <x-ui.empty icon="receipt" :title="__('No orders yet')" :description="__('Orders placed as a guest can be found with the tracking link in your confirmation e-mail.')">
            <x-ui.button :href="route('shop.index')">{{ __('Start shopping') }}</x-ui.button>
            <x-ui.button :href="route('orders.lookup')" variant="secondary">{{ __('Track a guest order') }}</x-ui.button>
        </x-ui.empty>
    @else
        <div class="card overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr><th>{{ __('Order') }}</th><th>{{ __('Placed') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Delivery') }}</th><th class="text-right">{{ __('Total') }}</th></tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td>
                                <a href="{{ route('account.orders.show', $order) }}" class="font-semibold hover:text-brand-700">{{ $order->number }}</a>
                                <p class="text-xs text-muted">{{ trans_choice(':count item|:count items', $order->items->sum('quantity'), ['count' => $order->items->sum('quantity')]) }}</p>
                            </td>
                            <td class="whitespace-nowrap">{{ $order->placed_at->translatedFormat('j M Y') }}</td>
                            <td><x-ui.badge :status="$order->payment_status" /></td>
                            <td><x-ui.badge :status="$order->fulfillment_status" /></td>
                            <td class="text-right font-medium"><x-ui.money :amount="$order->total_amount" :currency="$order->currency" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-layouts.dashboard>
