<x-layouts.dashboard :title="__('Users')" area="admin">
    <x-ui.page-header :title="__('Users')" :description="__('Customers, artists, partner staff and administrators.')" />
    <x-admin.table-card :rows="$users">
        <x-slot:toolbar>
            <form method="get" class="flex flex-wrap items-end gap-3">
                <label class="block text-xs font-medium text-muted">{{ __('Search') }}<input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Name or e-mail') }}" class="mt-1 block w-56 rounded-lg border border-line-strong px-3 py-1.5 text-sm text-ink"></label>
                <x-admin.filter name="role" :label="__('Role')" :options="$roles" :value="request('role')" />
                <x-admin.filter name="status" :label="__('Status')" :options="\App\Enums\UserStatus::options()" :value="request('status')" />
                <x-ui.button size="sm">{{ __('Apply') }}</x-ui.button>
            </form>
        </x-slot:toolbar>
        <thead><tr><th>{{ __('User') }}</th><th>{{ __('Roles') }}</th><th class="text-right">{{ __('Orders') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last sign-in') }}</th></tr></thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td><a href="{{ route('admin.users.show', $user) }}" class="font-medium hover:text-brand-700">{{ $user->name }}</a><p class="text-xs text-muted">{{ $user->email }}</p></td>
                    <td class="text-xs">{{ $user->roles->pluck('name')->implode(', ') }}</td>
                    <td class="text-right">{{ $user->orders_count }}</td>
                    <td><x-ui.badge :status="$user->status" /></td>
                    <td class="text-xs text-muted">{{ $user->last_login_at?->diffForHumans() ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
