@php $m = fn ($v) => \App\Support\Money::format((int) $v, $currency); @endphp
<x-layouts.dashboard :title="__('Finance')" area="admin">
    <x-ui.page-header :title="__('Finance & ledger')" :description="__('Balances are sums of append-only ledger entries (:cur). Corrections are posted as new adjustment entries, never edits.', ['cur' => $currency])">
        <x-slot:actions><x-ui.button variant="secondary" :href="route('admin.finance.export', request()->query())"><x-ui.icon name="download" class="size-4" /> {{ __('Export ledger CSV') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.stat :label="__('Platform revenue (all time)')" :value="$m($totals['platform.revenue'] ?? 0)" tone="clay" :hint="__('Margin + base cost + delivery − discounts − production cost − refunds − fees')" />
        <x-ui.stat :label="__('Artist earnings held')" :value="$m($totals['artist.held'] ?? 0)" tone="warning" :hint="__('Paid orders not yet past settlement')" />
        <x-ui.stat :label="__('Artist earnings available')" :value="$m($totals['artist.available'] ?? 0)" tone="leaf" :hint="__('Withdrawable, net of requested payouts')" />
        <x-ui.stat :label="__('Owed to printing partners')" :value="$m($totals['partner.payable'] ?? 0)" :hint="__('Production cost of dispatched jobs (settle off-platform)')" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-ui.card :title="__('Platform revenue by entry type')" :padding="false">
            <table class="table-base"><tbody>
                @foreach ($platformByType as $type => $total)
                    <tr><td>{{ \App\Enums\LedgerEntryType::tryFrom($type)?->label() ?? $type }}</td><td class="text-right tabular-nums {{ $total < 0 ? 'text-danger-700' : '' }}">{{ $m($total) }}</td></tr>
                @endforeach
            </tbody></table>
        </x-ui.card>
        <x-ui.card :title="__('Partner payables')" :padding="false">
            <table class="table-base"><tbody>
                @forelse ($partners as $row)
                    <tr><td>{{ $row['partner']->name }}</td><td class="text-right tabular-nums">{{ $m($row['payable']) }}</td></tr>
                @empty
                    <tr><td class="text-muted">{{ __('Nothing owed.') }}</td></tr>
                @endforelse
            </tbody></table>
        </x-ui.card>
        @can('finance.manage')
            <x-ui.card :title="__('Manual artist adjustment')" :description="__('Posts a correcting entry to the artist’s available balance, offset against platform revenue. Audited.')">
                <form method="post" action="{{ route('admin.finance.adjust') }}" class="space-y-3" onsubmit="return confirm(@js(__('Post this adjustment to the ledger?')))">
                    @csrf
                    <x-form.select name="artist_profile_id" :label="__('Artist')" :options="$allArtists" required />
                    <div class="grid grid-cols-2 gap-3">
                        <x-form.select name="direction" :label="__('Direction')" :options="['credit' => __('Credit artist'), 'debit' => __('Debit artist')]" />
                        <x-form.input name="amount" :label="__('Amount (:c)', ['c' => $currency])" inputmode="decimal" required />
                    </div>
                    <x-form.input name="reason" :label="__('Reason')" required />
                    <x-ui.button size="sm">{{ __('Post adjustment') }}</x-ui.button>
                </form>
            </x-ui.card>
        @endcan
    </div>

    <x-ui.card :title="__('Artist balances')" class="mt-6" :padding="false">
        <table class="table-base">
            <thead><tr><th>{{ __('Artist') }}</th><th class="text-right">{{ __('Held') }}</th><th class="text-right">{{ __('Available') }}</th></tr></thead>
            <tbody>
                @forelse ($artists as $artist)
                    <tr><td><a href="{{ route('admin.artists.show', $artist) }}" class="hover:text-brand-700">{{ $artist->display_name }}</a></td><td class="text-right tabular-nums">{{ $m($artist->held) }}</td><td class="text-right tabular-nums">{{ $m($artist->available) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">{{ __('No artist earnings yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.card>

    <div class="mt-6">
        <x-admin.table-card :rows="$entries" :empty="__('No ledger entries match.')">
            <x-slot:toolbar>
                <form method="get" class="flex flex-wrap items-end gap-3">
                    <x-admin.filter name="account_type" :label="__('Account')" :options="\App\Enums\LedgerAccount::options()" :value="request('account_type')" />
                    <x-admin.filter name="bucket" :label="__('Bucket')" :options="\App\Enums\LedgerBucket::options()" :value="request('bucket')" />
                    <x-admin.filter name="type" :label="__('Type')" :options="\App\Enums\LedgerEntryType::options()" :value="request('type')" />
                    <label class="block text-xs font-medium text-muted">{{ __('From') }}<input type="date" name="from" value="{{ request('from') }}" class="mt-1 block rounded-lg border border-line-strong px-2 py-1.5 text-sm text-ink"></label>
                    <label class="block text-xs font-medium text-muted">{{ __('To') }}<input type="date" name="to" value="{{ request('to') }}" class="mt-1 block rounded-lg border border-line-strong px-2 py-1.5 text-sm text-ink"></label>
                    <x-ui.button size="sm">{{ __('Apply') }}</x-ui.button>
                </form>
            </x-slot:toolbar>
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Account') }}</th><th>{{ __('Type') }}</th><th>{{ __('Description') }}</th><th class="text-right">{{ __('Amount') }}</th></tr></thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td class="whitespace-nowrap text-xs text-muted">{{ $entry->created_at->translatedFormat('j M Y H:i') }}</td>
                        <td class="text-xs">{{ $entry->account_type->label() }}@if ($entry->account_id) #{{ $entry->account_id }}@endif · {{ $entry->bucket->label() }}</td>
                        <td><x-ui.badge :status="$entry->type" /></td>
                        <td class="text-xs">{{ $entry->description }}@if ($entry->order) · <a href="{{ route('admin.orders.show', $entry->order) }}" class="underline">{{ $entry->order->number }}</a>@endif</td>
                        <td class="text-right tabular-nums {{ $entry->amount < 0 ? 'text-danger-700' : '' }}"><x-ui.money :amount="$entry->amount" :currency="$entry->currency" /></td>
                    </tr>
                @endforeach
            </tbody>
        </x-admin.table-card>
    </div>
</x-layouts.dashboard>
