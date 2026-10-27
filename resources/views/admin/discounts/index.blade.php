<x-layouts.dashboard :title="__('Discounts')" area="admin">
    <x-ui.page-header :title="__('Discount codes')" :description="__('Discounts are funded by the platform: artist earnings are never reduced by promotions.')">
        <x-slot:actions><x-ui.button :href="route('admin.discounts.create')"><x-ui.icon name="plus" class="size-4" /> {{ __('New code') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-admin.table-card :rows="$discounts" :empty="__('No discount codes.')">
        <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Value') }}</th><th>{{ __('Used') }}</th><th class="text-right">{{ __('Given away') }}</th><th>{{ __('Validity') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @foreach ($discounts as $discount)
                <tr>
                    <td><a href="{{ route('admin.discounts.edit', $discount) }}" class="font-mono font-semibold hover:text-brand-700">{{ $discount->code }}</a><p class="text-xs text-muted">{{ $discount->description }}</p></td>
                    <td>{{ $discount->valueLabel() }}@if ($discount->min_subtotal_amount)<p class="text-xs text-muted">{{ __('min. :a', ['a' => \App\Support\Money::format($discount->min_subtotal_amount, $discount->currency)]) }}</p>@endif</td>
                    <td>{{ $discount->redemptions_count }}{{ $discount->max_redemptions ? ' / '.$discount->max_redemptions : '' }}</td>
                    <td class="text-right"><x-ui.money :amount="(int) $discount->redemptions_sum_amount" :currency="$discount->currency" /></td>
                    <td class="text-xs text-muted">{{ $discount->starts_at?->translatedFormat('j M Y') ?? '…' }} – {{ $discount->ends_at?->translatedFormat('j M Y') ?? '…' }}</td>
                    <td><x-ui.badge :tone="$discount->is_active ? 'success' : 'neutral'">{{ $discount->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
