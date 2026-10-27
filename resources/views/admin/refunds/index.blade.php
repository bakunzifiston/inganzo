<x-layouts.dashboard :title="__('Refunds')" area="admin">
    <x-ui.page-header :title="__('Refunds')" :description="__('Requests are approved, then the money is sent through the provider and the transfer reference recorded. Ledger reversals are posted automatically when a refund is processed.')" />
    <x-admin.table-card :rows="$refunds" :empty="__('No refunds.')">
        <x-slot:toolbar>
            <form method="get"><x-admin.filter name="status" :label="__('Status')" :options="\App\Enums\RefundStatus::options()" :value="request('status')" /></form>
        </x-slot:toolbar>
        <thead><tr><th>{{ __('Refund') }}</th><th>{{ __('Order') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Amount') }}</th><th>{{ __('Actions') }}</th></tr></thead>
        <tbody>
            @foreach ($refunds as $refund)
                <tr>
                    <td><p class="font-medium">{{ $refund->reference }}</p><p class="text-xs text-muted">{{ $refund->created_at->translatedFormat('j M Y') }} · {{ $refund->requester?->name ?? __('staff') }}</p></td>
                    <td><a href="{{ route('admin.orders.show', $refund->order) }}" class="hover:text-brand-700">{{ $refund->order->number }}</a>@if ($refund->orderItem)<p class="text-xs text-muted">{{ $refund->orderItem->title }}</p>@endif</td>
                    <td>{{ $refund->reason->label() }}@if ($refund->customer_note)<p class="max-w-56 truncate text-xs text-muted" title="{{ $refund->customer_note }}">{{ $refund->customer_note }}</p>@endif</td>
                    <td><x-ui.badge :status="$refund->status" />@if ($refund->provider_reference)<p class="mt-1 font-mono text-xs text-muted">{{ $refund->provider_reference }}</p>@endif</td>
                    <td class="text-right"><x-ui.money :amount="$refund->amount" :currency="$refund->currency" /></td>
                    <td>@include('admin.refunds.partials.actions', ['refund' => $refund])</td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
