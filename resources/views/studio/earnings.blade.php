<x-layouts.dashboard :title="__('Earnings & payouts')" area="studio">
    <x-ui.page-header :title="__('Earnings & payouts')" :description="__('Earnings are held until the item is delivered and :days days have passed, then become available to withdraw.', ['days' => $holdDays])" />

    <div class="grid gap-4 sm:grid-cols-3">
        <x-ui.stat :label="__('Available to withdraw')" :value="\App\Support\Money::format($eligibility['available'], $eligibility['currency'])" tone="leaf" />
        <x-ui.stat :label="__('Pending (held)')" :value="\App\Support\Money::format($held, $eligibility['currency'])" :hint="__('Paid orders not yet settled')" />
        <x-ui.stat :label="__('Minimum payout')" :value="\App\Support\Money::format($eligibility['minimum'], $eligibility['currency'])" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Request a payout')">
            @if ($eligibility['eligible'])
                <form method="post" action="{{ route('studio.earnings.payouts.store') }}" class="flex items-end gap-3">
                    @csrf
                    <x-form.input name="amount" type="number" :label="__('Amount (:currency)', ['currency' => $eligibility['currency']])" :value="$eligibility['available']" min="{{ $eligibility['minimum'] }}" max="{{ $eligibility['available'] }}" class="flex-1" />
                    <x-ui.button variant="accent">{{ __('Request') }}</x-ui.button>
                </form>
                <p class="mt-2 text-xs text-muted">{{ __('Paid to :account', ['account' => $account->maskedDisplay()]) }}</p>
            @else
                <ul class="space-y-1.5 text-sm text-muted">
                    @foreach ($eligibility['reasons'] as $reason)<li class="flex gap-2"><x-ui.icon name="info" class="mt-0.5 size-4 shrink-0 text-subtle" /> {{ $reason }}</li>@endforeach
                </ul>
                @error('amount')<p class="mt-2 text-sm text-danger-700">{{ $message }}</p>@enderror
            @endif
        </x-ui.card>

        <x-ui.card :title="__('Payout account')" :description="$account ? ($account->isVerified() ? __('Verified') : __('Awaiting verification by our finance team')) : __('Where we send your money')">
            <form method="post" action="{{ route('studio.earnings.account') }}" class="grid gap-4 sm:grid-cols-2" x-data="{ method: '{{ old('method', $account?->method->value ?? 'mtn_momo') }}' }">
                @csrf @method('put')
                <x-form.select name="method" :label="__('Method')" :options="\App\Enums\PayoutMethod::options()" :value="$account?->method" x-model="method" />
                <x-form.input name="account_name" :label="__('Account holder name')" :value="$account?->account_name" required />
                <x-form.input name="account_number" :label="__('Phone or account number')" :placeholder="$account ? '••••'.$account->account_last4 : '078 123 4567'" required />
                <div x-show="method === 'bank_transfer'"><x-form.input name="bank_name" :label="__('Bank')" :value="$account?->bank_name" /></div>
                <div class="sm:col-span-2"><x-ui.button variant="secondary">{{ $account ? __('Replace account') : __('Save account') }}</x-ui.button></div>
            </form>
        </x-ui.card>
    </div>

    @if ($payoutRequests->isNotEmpty())
        <x-ui.card :title="__('Payout history')" :padding="false" class="mt-6">
            <table class="table-base">
                <thead><tr><th>{{ __('Reference') }}</th><th>{{ __('Requested') }}</th><th>{{ __('To') }}</th><th class="text-right">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>
                    @foreach ($payoutRequests as $payout)
                        <tr>
                            <td class="font-mono text-xs">{{ $payout->reference }}</td>
                            <td class="text-muted">{{ $payout->created_at->translatedFormat('j M Y') }}</td>
                            <td class="text-muted">{{ $payout->account_display }}</td>
                            <td class="text-right font-medium"><x-ui.money :amount="$payout->amount" :currency="$payout->currency" /></td>
                            <td><x-ui.badge :status="$payout->status" />@if ($payout->admin_note && in_array($payout->status->value, ['rejected', 'failed']))<span class="block text-xs text-muted">{{ $payout->admin_note }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.card>
    @endif

    <x-ui.card :title="__('Ledger')" :description="__('Every change to your balance. Entries are permanent; corrections appear as new lines.')" :padding="false" class="mt-6">
        @if ($entries->isEmpty())
            <p class="p-6 text-sm text-muted">{{ __('No transactions yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th>{{ __('Balance') }}</th><th class="text-right">{{ __('Amount') }}</th></tr></thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="whitespace-nowrap text-muted">{{ $entry->created_at->translatedFormat('j M Y') }}</td>
                                <td><x-ui.badge :status="$entry->type" /></td>
                                <td class="text-muted">{{ $entry->description }}</td>
                                <td class="text-muted">{{ $entry->bucket->label() }}</td>
                                <td @class(['text-right font-medium tabular-nums', 'text-success-700' => $entry->amount > 0, 'text-danger-700' => $entry->amount < 0])>{{ $entry->amount > 0 ? '+' : '' }}<x-ui.money :amount="$entry->amount" :currency="$entry->currency" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $entries->links() }}</div>
        @endif
    </x-ui.card>
</x-layouts.dashboard>
