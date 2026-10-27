<x-layouts.dashboard :title="$artist->display_name" area="admin">
    <x-ui.page-header :eyebrow="__('Artist')" :title="$artist->display_name" :description="$artist->user->name.' · '.$artist->user->email">
        <x-slot:actions>
            <x-ui.badge :status="$artist->status" />
            @if ($artist->isApproved())<a href="{{ route('artists.show', $artist) }}" target="_blank" class="text-sm text-muted underline">{{ __('Public shop') }}</a>@endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-4 sm:grid-cols-4">
        <x-ui.stat :label="__('Units sold')" :value="number_format($unitsSold)" :hint="__('Paid orders, all time')" />
        <x-ui.stat :label="__('Lifetime earnings')" :value="\App\Support\Money::format($lifetime, 'RWF')" :hint="__('Before refunds')" />
        <x-ui.stat :label="__('Held')" :value="\App\Support\Money::format($held, 'RWF')" tone="warning" :hint="__('Awaiting delivery + hold period')" />
        <x-ui.stat :label="__('Available')" :value="\App\Support\Money::format($available, 'RWF')" tone="leaf" :hint="__('Withdrawable now')" />
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="space-y-6">
            <x-ui.card :title="__('Designs')" :padding="false">
                <table class="table-base">
                    <thead><tr><th>{{ __('Design') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Sales') }}</th></tr></thead>
                    <tbody>
                        @forelse ($artworks as $artwork)
                            <tr><td><a href="{{ route('admin.moderation.show', $artwork) }}" class="flex items-center gap-3 hover:text-brand-700">@if ($artwork->currentVersion?->isReady())<img src="{{ $artwork->currentVersion->thumbnailUrl() }}" alt="" class="size-10 rounded bg-sand object-contain">@endif {{ $artwork->title }}</a></td><td><x-ui.badge :status="$artwork->status" /></td><td class="text-right tabular-nums">{{ $artwork->sales_count }}</td></tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">{{ __('No designs uploaded yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.card>
            @can('finance.view')
                <x-ui.card :title="__('Recent ledger entries')" :padding="false">
                    <table class="table-base">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Bucket') }}</th><th>{{ __('Description') }}</th><th class="text-right">{{ __('Amount') }}</th></tr></thead>
                        <tbody>
                            @forelse ($entries as $entry)
                                <tr><td class="whitespace-nowrap text-xs text-muted">{{ $entry->created_at->translatedFormat('j M Y') }}</td><td><x-ui.badge :status="$entry->type" /></td><td class="text-xs">{{ $entry->bucket->label() }}</td><td class="text-xs text-muted">{{ $entry->description }}</td><td class="text-right tabular-nums {{ $entry->amount < 0 ? 'text-danger-700' : '' }}"><x-ui.money :amount="$entry->amount" :currency="$entry->currency" /></td></tr>
                            @empty
                                <tr><td colspan="5" class="text-muted">{{ __('No ledger activity.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.card>
            @endcan
        </div>
        <aside class="space-y-6">
            @can('artists.manage')
                <x-ui.card :title="__('Account status')">
                    <form method="post" action="{{ route('admin.artists.status', $artist) }}" class="space-y-3">
                        @csrf
                        <x-form.select name="status" :label="__('Status')" :options="\App\Enums\ArtistStatus::options()" :value="$artist->status" />
                        <x-form.textarea name="reason" :label="__('Reason (required to reject or suspend)')" rows="2" :value="$artist->status_reason" />
                        <x-ui.button size="sm">{{ __('Update status') }}</x-ui.button>
                    </form>
                    <div class="mt-4 border-t border-line pt-4">
                        <x-admin.inline-form :action="route('admin.artists.feature', $artist)" :label="$artist->is_featured ? __('Remove from featured') : __('Feature on homepage')" />
                    </div>
                    <dl class="mt-4 space-y-1 text-xs text-muted">
                        <div>{{ __('Agreement :v accepted :d', ['v' => $artist->agreement_version, 'd' => $artist->agreement_accepted_at?->translatedFormat('j M Y') ?? '—']) }}</div>
                        @if ($artist->approved_at)<div>{{ __('Approved :d', ['d' => $artist->approved_at->translatedFormat('j M Y')]) }}</div>@endif
                    </dl>
                </x-ui.card>
            @endcan
            <x-ui.card :title="__('Payout accounts')">
                @forelse ($artist->payoutAccounts as $account)
                    <div class="mb-3 rounded-xl border border-line p-3 text-sm">
                        <p class="font-medium">{{ $account->maskedDisplay() }}</p>
                        <p class="text-xs text-muted">{{ $account->is_default ? __('Default') : '' }} @if ($account->bank_name) · {{ $account->bank_name }}@endif</p>
                        @if ($account->isVerified())
                            <x-ui.badge tone="success" class="mt-2">{{ __('Verified :d', ['d' => $account->verified_at->translatedFormat('j M Y')]) }}</x-ui.badge>
                        @else
                            @can('payouts.manage')<x-admin.inline-form :action="route('admin.payout-accounts.verify', $account)" :label="__('Mark verified')" :confirm="__('Confirm you checked the account holder name matches the artist.')" class="mt-2 block" />@endcan
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('No payout account added.') }}</p>
                @endforelse
            </x-ui.card>
            <x-ui.card :title="__('Profile')">
                <p class="text-sm whitespace-pre-line text-muted">{{ $artist->bio ?: '—' }}</p>
                <p class="mt-2 text-xs text-muted">{{ $artist->location }}</p>
            </x-ui.card>
        </aside>
    </div>
</x-layouts.dashboard>
