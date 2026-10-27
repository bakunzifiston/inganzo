@php
    $filterUrl = fn (array $overrides) => route('studio.orders.index', array_filter([...request()->only(['type', 'state', 'q']), ...$overrides, 'page' => null], fn ($v) => $v !== null && $v !== ''));
    $pill = fn (bool $on) => $on ? 'bg-brand-600 text-ink' : 'bg-white ring-1 ring-line hover:ring-ink';
@endphp
<x-layouts.dashboard :title="__('Orders')" area="studio">
    <x-ui.page-header :title="__('Orders')" :description="__('Orders containing your products. Ship ready-made items yourself; printed items are handled by our partners.')" />

    @if ($toShip > 0)
        <a href="{{ $filterUrl(['state' => 'to_ship', 'type' => 'finished']) }}" class="mb-6 flex items-center gap-3 rounded-2xl border border-warning-100 bg-warning-50 p-4 text-sm hover:shadow-[var(--shadow-lift)]">
            <x-ui.icon name="truck" class="size-5 text-warning-700" />
            <span class="font-semibold">{{ trans_choice(':count ready-made item is waiting to be shipped|:count ready-made items are waiting to be shipped', $toShip, ['count' => $toShip]) }}</span>
            <x-ui.icon name="arrow-right" class="ml-auto size-4" />
        </a>
    @endif

    <div class="mb-6 flex flex-wrap items-center gap-2">
        <a href="{{ $filterUrl(['type' => null]) }}" @class(['rounded-full px-3.5 py-1.5 text-sm font-medium', $pill($filters['type'] === '')])>{{ __('All types') }}</a>
        @foreach (\App\Enums\SellingType::cases() as $case)
            <a href="{{ $filterUrl(['type' => $case->value]) }}" @class(['rounded-full px-3.5 py-1.5 text-sm font-medium', $pill($filters['type'] === $case->value)])>{{ $case->label() }}</a>
        @endforeach
        <span class="mx-1 h-5 w-px bg-line"></span>
        @foreach (['to_ship' => __('To ship'), 'in_progress' => __('In production'), 'shipped' => __('Shipped'), 'delivered' => __('Delivered'), 'closed' => __('Cancelled / refunded')] as $key => $label)
            <a href="{{ $filterUrl(['state' => $filters['state'] === $key ? null : $key]) }}" @class(['rounded-full px-3 py-1 text-xs font-medium', $pill($filters['state'] === $key)])>{{ $label }}</a>
        @endforeach
        <form method="get" class="ml-auto">
            @foreach (['type', 'state'] as $keep)@if ($filters[$keep] !== '')<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif @endforeach
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Order number') }}" class="input w-48" aria-label="{{ __('Order number') }}">
        </form>
    </div>

    <x-ui.card :padding="false">
        @if ($orders->isEmpty())
            <div class="p-6"><x-ui.empty icon="receipt" :title="__('No orders yet')" :description="__('Orders appear here as soon as customers pay for your products.')" /></div>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Placed') }}</th><th>{{ __('Items') }}</th><th class="text-right">{{ __('Your earning') }}</th><th>{{ __('Payment') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><a href="{{ route('studio.orders.show', $order) }}" class="font-mono text-xs font-medium hover:text-brand-700">{{ $order->number }}</a></td>
                                <td class="whitespace-nowrap text-muted">{{ $order->placed_at->translatedFormat('j M Y') }}</td>
                                <td>
                                    <ul class="space-y-1">
                                        @foreach ($order->items as $item)
                                            <li class="flex items-center gap-2"><x-ui.badge :status="$item->selling_type" /> <span class="truncate">{{ $item->title }} × {{ $item->quantity }}</span></li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="text-right font-medium"><x-ui.money :amount="$order->items->sum(fn ($i) => $i->artistEarningTotal())" :currency="$order->currency" /></td>
                                <td><x-ui.badge :status="$order->payment_status" /></td>
                                <td>@foreach ($order->items->pluck('fulfillment_status')->unique() as $s)<x-ui.badge :status="$s" class="mb-1" />@endforeach</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $orders->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.dashboard>
