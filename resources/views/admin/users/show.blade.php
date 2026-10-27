<x-layouts.dashboard :title="$user->name" area="admin">
    <x-ui.page-header :eyebrow="__('User')" :title="$user->name" :description="$user->email.($user->phone ? ' · '.\App\Support\PhoneNumber::display($user->phone) : '').' · '.__('joined :d', ['d' => $user->created_at->translatedFormat('j M Y')])">
        <x-slot:actions><x-ui.badge :status="$user->status" />@unless ($user->email_verified_at)<x-ui.badge tone="warning">{{ __('E-mail not verified') }}</x-ui.badge>@endunless</x-slot:actions>
    </x-ui.page-header>
    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="space-y-6">
            <x-ui.card :title="__('Recent orders')" :padding="false">
                <table class="table-base"><tbody>
                    @forelse ($orders as $order)
                        <tr><td><a href="{{ route('admin.orders.show', $order) }}" class="font-medium hover:text-brand-700">{{ $order->number }}</a></td><td class="text-muted">{{ $order->placed_at->translatedFormat('j M Y') }}</td><td><x-ui.badge :status="$order->payment_status" /></td><td class="text-right"><x-ui.money :amount="$order->total_amount" :currency="$order->currency" /></td></tr>
                    @empty
                        <tr><td class="text-muted">{{ __('No orders.') }}</td></tr>
                    @endforelse
                </tbody></table>
            </x-ui.card>
            <x-ui.card :title="__('Actions performed by this user')" :padding="false">
                <table class="table-base"><tbody>
                    @forelse ($activity as $log)
                        <tr><td class="text-xs text-muted">{{ $log->created_at->translatedFormat('j M H:i') }}</td><td class="text-xs font-medium">{{ $log->action }}</td><td class="max-w-sm truncate text-xs text-subtle">{{ $log->changes ? json_encode($log->changes) : '' }}</td></tr>
                    @empty
                        <tr><td class="text-sm text-muted">{{ __('No audited actions.') }}</td></tr>
                    @endforelse
                </tbody></table>
            </x-ui.card>
        </div>
        <aside class="space-y-6">
            @if ($user->artistProfile)
                <x-ui.card :title="__('Artist shop')"><a href="{{ route('admin.artists.show', $user->artistProfile) }}" class="link">{{ $user->artistProfile->display_name }}</a> <x-ui.badge :status="$user->artistProfile->status" class="ml-2" /></x-ui.card>
            @endif
            @if ($user->partnerMembership)
                <x-ui.card :title="__('Printing partner')"><a href="{{ route('admin.partners.show', $user->partnerMembership->partner) }}" class="link">{{ $user->partnerMembership->partner->name }}</a> <span class="text-xs text-muted">({{ $user->partnerMembership->role }})</span></x-ui.card>
            @endif
            @can('users.manage')
                <x-ui.card :title="__('Account access')">
                    @if ($user->isSuspended())
                        <p class="text-sm text-muted">{{ __('Suspended :d: :r', ['d' => $user->suspended_at?->translatedFormat('j M Y'), 'r' => $user->suspension_reason]) }}</p>
                        <x-admin.inline-form :action="route('admin.users.reactivate', $user)" :label="__('Reactivate account')" variant="success" class="mt-3 block" />
                    @else
                        <form method="post" action="{{ route('admin.users.suspend', $user) }}" class="space-y-3" onsubmit="return confirm(@js(__('Suspend this account? The user is signed out immediately.')))">
                            @csrf
                            <x-form.input name="reason" :label="__('Reason')" required />
                            <x-ui.button size="sm" variant="danger">{{ __('Suspend account') }}</x-ui.button>
                        </form>
                    @endif
                </x-ui.card>
            @endcan
            <x-ui.card :title="__('Roles')">
                @can('staff.manage')
                    <form method="post" action="{{ route('admin.users.roles', $user) }}" class="space-y-2">
                        @csrf @method('put')
                        @foreach ($roles as $role)
                            <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked($user->roles->contains($role)) class="mt-0.5 accent-brand-600"><span>{{ $role->name }}@if ($role->is_staff) <span class="text-xs text-brand-700">{{ __('staff') }}</span>@endif</span></label>
                        @endforeach
                        <x-ui.button size="sm" class="mt-2">{{ __('Save roles') }}</x-ui.button>
                    </form>
                @else
                    <p class="text-sm">{{ $user->roles->pluck('name')->implode(', ') ?: '—' }}</p>
                @endcan
            </x-ui.card>
            <x-ui.card :title="__('Addresses')">
                @forelse ($user->addresses as $address)<p class="mb-2 text-sm">{{ $address->recipient_name }} — <span class="text-muted">{{ $address->summary() }}</span></p>@empty<p class="text-sm text-muted">{{ __('None saved.') }}</p>@endforelse
            </x-ui.card>
        </aside>
    </div>
</x-layouts.dashboard>
