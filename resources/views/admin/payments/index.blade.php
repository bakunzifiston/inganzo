<x-layouts.dashboard :title="__('Payments')" area="admin">
    <x-ui.page-header :title="__('Payments')" :description="__('Every payment attempt and its provider status. Orders are only marked paid from verified provider responses.')" />
    @if ($stalePending || $duplicates)
        <div class="mb-6 grid gap-3 sm:grid-cols-2">
            @if ($stalePending)<div class="rounded-xl border border-warning-700/20 bg-warning-50 px-4 py-3 text-sm text-warning-700">{{ trans_choice(':count payment has been pending for over 30 minutes. Check its status with the provider.|:count payments have been pending for over 30 minutes. Check their status with the provider.', $stalePending, ['count' => $stalePending]) }}</div>@endif
            @if ($duplicates)<div class="rounded-xl border border-danger-700/20 bg-danger-50 px-4 py-3 text-sm text-danger-700">{{ trans_choice(':count duplicate payment needs to be refunded to the customer.|:count duplicate payments need to be refunded to customers.', $duplicates, ['count' => $duplicates]) }} <a href="{{ route('admin.payments.index', ['status' => 'duplicate']) }}" class="underline">{{ __('View') }}</a></div>@endif
        </div>
    @endif
    <x-admin.table-card :rows="$payments">
        <x-slot:toolbar>
            <form method="get" class="flex flex-wrap items-end gap-3">
                <label class="block text-xs font-medium text-muted">{{ __('Search') }}<input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Order or reference') }}" class="mt-1 block w-56 rounded-lg border border-line-strong px-3 py-1.5 text-sm text-ink"></label>
                <x-admin.filter name="status" :label="__('Status')" :options="\App\Enums\PaymentStatus::options()" :value="request('status')" />
                <x-admin.filter name="provider" :label="__('Provider')" :options="$providers->combine($providers)" :value="request('provider')" />
                <x-ui.button size="sm">{{ __('Apply') }}</x-ui.button>
            </form>
        </x-slot:toolbar>
        <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Provider') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Status') }}</th><th>{{ __('Created') }}</th><th class="text-right">{{ __('Amount') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($payments as $payment)
                <tr>
                    <td><a href="{{ route('admin.orders.show', $payment->order) }}" class="font-medium hover:text-brand-700">{{ $payment->order->number }}</a></td>
                    <td>{{ $payment->provider }}</td>
                    <td class="max-w-40 truncate font-mono text-xs">{{ $payment->provider_reference ?? '—' }}</td>
                    <td><x-ui.badge :status="$payment->status" />@if ($payment->failure_reason)<p class="mt-1 max-w-56 truncate text-xs text-danger-700" title="{{ $payment->failure_reason }}">{{ $payment->failure_reason }}</p>@endif</td>
                    <td class="whitespace-nowrap text-muted">{{ $payment->created_at->translatedFormat('j M H:i') }}</td>
                    <td class="text-right"><x-ui.money :amount="$payment->amount" :currency="$payment->currency" /></td>
                    <td class="text-right">@if ($payment->status->value === 'pending' && $payment->provider !== 'cash_on_delivery')<x-admin.inline-form :action="route('admin.payments.refresh', $payment)" :label="__('Check')" />@endif</td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
