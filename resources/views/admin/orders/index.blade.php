<x-layouts.dashboard :title="__('Orders')" area="admin">
    <x-ui.page-header :title="__('Orders')" :description="__('Search, filter and manage every order. Statuses for payment, production and delivery are tracked separately.')">
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('admin.orders.export', request()->query())"><x-ui.icon name="download" class="size-4" /> {{ __('Export CSV') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <x-admin.table-card :rows="$orders" :empty="__('No orders match these filters.')">
        <x-slot:toolbar>
            <form method="get" class="flex flex-wrap items-end gap-3">
                <label class="block text-xs font-medium text-muted">{{ __('Search') }}
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('Number, e-mail, name, phone') }}" class="mt-1 block w-60 rounded-lg border border-line-strong bg-white px-3 py-1.5 text-sm text-ink">
                </label>
                <x-admin.filter name="status" :label="__('Order')" :options="\App\Enums\OrderStatus::options()" :value="$filters['status'] ?? null" />
                <x-admin.filter name="payment_status" :label="__('Payment')" :options="\App\Enums\OrderPaymentStatus::options()" :value="$filters['payment_status'] ?? null" />
                <x-admin.filter name="fulfillment_status" :label="__('Fulfilment')" :options="\App\Enums\FulfillmentStatus::options()" :value="$filters['fulfillment_status'] ?? null" />
                <label class="block text-xs font-medium text-muted">{{ __('From') }}<input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block rounded-lg border border-line-strong bg-white px-2 py-1.5 text-sm text-ink"></label>
                <label class="block text-xs font-medium text-muted">{{ __('To') }}<input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block rounded-lg border border-line-strong bg-white px-2 py-1.5 text-sm text-ink"></label>
                <x-ui.button size="sm">{{ __('Apply') }}</x-ui.button>
                @if (array_filter($filters))<a href="{{ route('admin.orders.index') }}" class="text-sm text-muted underline">{{ __('Reset') }}</a>@endif
            </form>
        </x-slot:toolbar>
        <thead>
            <tr><th>{{ __('Order') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Placed') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Fulfilment') }}</th><th class="text-right">{{ __('Total') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($orders as $order)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $order) }}" class="font-semibold hover:text-brand-700">{{ $order->number }}</a><p class="text-xs text-muted">{{ trans_choice(':count item|:count items', $order->items_count, ['count' => $order->items_count]) }} · {{ $order->payment_method }}</p></td>
                    <td><p>{{ $order->customer_name }}</p><p class="text-xs text-muted">{{ $order->email }}@if (! $order->user_id) · {{ __('guest') }}@endif</p></td>
                    <td class="whitespace-nowrap text-muted">{{ $order->placed_at->translatedFormat('j M Y H:i') }}</td>
                    <td><x-ui.badge :status="$order->payment_status" /></td>
                    <td class="space-y-1"><x-ui.badge :status="$order->fulfillment_status" /> @if ($order->status->value === 'cancelled')<x-ui.badge :status="$order->status" />@endif</td>
                    <td class="text-right font-medium"><x-ui.money :amount="$order->total_amount" :currency="$order->currency" /></td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
