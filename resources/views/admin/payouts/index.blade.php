<x-layouts.dashboard :title="__('Payouts')" area="admin">
    <x-ui.page-header :title="__('Artist payouts')" :description="__('Requested amounts are already reserved from the artist’s available balance. Approve, send the transfer, then record its reference. Rejecting or failing returns the money to the balance.')" />

    @if ($unverified->isNotEmpty())
        <x-ui.card :title="__('Payout accounts awaiting verification')" class="mb-6" :padding="false">
            <ul class="divide-y divide-line text-sm">
                @foreach ($unverified as $account)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                        <span><a href="{{ route('admin.artists.show', $account->artistProfile) }}" class="font-medium hover:text-brand-700">{{ $account->artistProfile->display_name }}</a> <span class="text-muted">· {{ $account->maskedDisplay() }}</span></span>
                        <x-admin.inline-form :action="route('admin.payout-accounts.verify', $account)" :label="__('Mark verified')" :confirm="__('Confirm the account holder name matches the artist.')" />
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <x-admin.table-card :rows="$payouts" :empty="__('No payout requests.')">
        <x-slot:toolbar><form method="get"><x-admin.filter name="status" :label="__('Status')" :options="\App\Enums\PayoutStatus::options()" :value="request('status')" /></form></x-slot:toolbar>
        <thead><tr><th>{{ __('Payout') }}</th><th>{{ __('Artist') }}</th><th>{{ __('Destination') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Amount') }}</th><th>{{ __('Actions') }}</th></tr></thead>
        <tbody>
            @foreach ($payouts as $payout)
                <tr>
                    <td class="font-medium">{{ $payout->reference }}<p class="text-xs font-normal text-muted">{{ $payout->created_at->translatedFormat('j M Y') }}</p></td>
                    <td><a href="{{ route('admin.artists.show', $payout->artist) }}" class="hover:text-brand-700">{{ $payout->artist->display_name }}</a></td>
                    <td class="text-xs">{{ $payout->account_display }}@if ($payout->payoutAccount && ! $payout->payoutAccount->isVerified())<p class="text-warning-700">{{ __('Account not verified') }}</p>@endif</td>
                    <td><x-ui.badge :status="$payout->status" />@foreach ($payout->transactions as $tx)<p class="mt-1 font-mono text-xs text-muted">{{ $tx->reference }}</p>@endforeach @if ($payout->admin_note)<p class="mt-1 max-w-48 truncate text-xs text-muted" title="{{ $payout->admin_note }}">{{ $payout->admin_note }}</p>@endif</td>
                    <td class="text-right font-medium"><x-ui.money :amount="$payout->amount" :currency="$payout->currency" /></td>
                    <td class="space-y-2">
                        @if ($payout->status->value === 'requested')
                            <x-admin.inline-form :action="route('admin.payouts.approve', $payout)" :label="__('Approve')" variant="success" />
                        @endif
                        @if ($payout->status->value === 'approved')
                            <form method="post" action="{{ route('admin.payouts.paid', $payout) }}" class="flex flex-wrap gap-1">
                                @csrf
                                <select name="provider" class="rounded-lg border border-line-strong py-1 pr-7 pl-2 text-xs">@foreach (\App\Enums\PayoutMethod::options() as $k => $l)<option value="{{ $k }}" @selected($k === $payout->method->value)>{{ $l }}</option>@endforeach</select>
                                <input name="reference" required placeholder="{{ __('Transfer ref.') }}" class="w-28 rounded-lg border border-line-strong px-2 py-1 text-xs">
                                <x-ui.button size="sm" variant="success">{{ __('Paid') }}</x-ui.button>
                            </form>
                        @endif
                        @if (in_array($payout->status->value, ['requested', 'approved'], true))
                            <form method="post" action="{{ route($payout->status->value === 'approved' ? 'admin.payouts.failed' : 'admin.payouts.reject', $payout) }}" class="flex gap-1">
                                @csrf
                                <input name="note" required placeholder="{{ __('Reason') }}" class="w-28 rounded-lg border border-line-strong px-2 py-1 text-xs">
                                <x-ui.button size="sm" variant="secondary">{{ $payout->status->value === 'approved' ? __('Failed') : __('Reject') }}</x-ui.button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
