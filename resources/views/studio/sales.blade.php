<x-layouts.dashboard :title="__('Sales')" area="studio">
    <x-ui.page-header :title="__('Sales')" :description="__('Every item sold from your shop, printed or ready-made. Only paid orders are listed.')" />
    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <x-ui.stat :label="__('Units sold (all time)')" :value="number_format($totals['units'])" />
        <x-ui.stat :label="__('Earnings (all time)')" :value="\App\Support\Money::format($totals['earnings'], 'RWF')" tone="clay" :hint="__('Before refunds. See Earnings for your balance.')" />
    </div>
    <x-ui.card :padding="false">
        <x-slot:actions><form method="get"><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search items') }}" class="input w-56" aria-label="{{ __('Search items') }}"></form></x-slot:actions>
        @if ($items->isEmpty())
            <div class="p-6"><x-ui.empty icon="receipt" :title="__('No sales yet')" :description="__('Share your shop link on social media to get your first order.')" /></div>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Order') }}</th><th>{{ __('Item') }}</th><th>{{ __('Type') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('Your earning') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td class="whitespace-nowrap text-muted">{{ $item->order->paid_at?->translatedFormat('j M Y') }}</td>
                                <td class="font-mono text-xs">{{ $item->order->number }}</td>
                                <td class="font-medium">{{ $item->title }}</td>
                                <td><x-ui.badge :status="$item->selling_type" /><p class="mt-0.5 text-xs text-muted">{{ $item->isFinished() ? $item->variantLabel() : $item->product_name.' · '.$item->variantLabel() }}</p></td>
                                <td class="text-right">{{ $item->quantity }}</td>
                                <td class="text-right font-medium"><x-ui.money :amount="$item->artistEarningTotal()" :currency="$item->order->currency" />@if ($item->refunded_amount)<span class="block text-xs text-danger-700">{{ __('partly refunded') }}</span>@endif</td>
                                <td><x-ui.badge :status="$item->fulfillment_status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $items->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.dashboard>
