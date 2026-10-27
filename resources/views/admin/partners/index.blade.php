<x-layouts.dashboard :title="__('Printing partners')" area="admin">
    <x-ui.page-header :title="__('Printing partners')" :description="__('Paid items are routed automatically to the active partner with the lowest load that prints the product and serves the delivery area. Lower priority numbers win ties.')">
        <x-slot:actions><x-ui.button :href="route('admin.partners.create')"><x-ui.icon name="plus" class="size-4" /> {{ __('New partner') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-admin.table-card :rows="$partners" :empty="__('No printing partners yet. Orders cannot go to production until one is added.')">
        <thead><tr><th>{{ __('Partner') }}</th><th>{{ __('Location') }}</th><th class="text-right">{{ __('Products') }}</th><th class="text-right">{{ __('Open jobs') }}</th><th class="text-right">{{ __('Capacity/day') }}</th><th>{{ __('Priority') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @foreach ($partners as $partner)
                <tr>
                    <td><a href="{{ route('admin.partners.show', $partner) }}" class="font-medium hover:text-brand-700">{{ $partner->name }}</a><p class="text-xs text-muted">{{ trans_choice(':count staff member|:count staff members', $partner->members_count, ['count' => $partner->members_count]) }}</p></td>
                    <td>{{ $partner->city }}, {{ $partner->country->name }}</td>
                    <td class="text-right">{{ $partner->services_count }}</td>
                    <td class="text-right">{{ $partner->open_jobs_count }}@if ($partner->delayed_jobs_count)<span class="text-danger-700"> ({{ __(':n late', ['n' => $partner->delayed_jobs_count]) }})</span>@endif</td>
                    <td class="text-right">{{ $partner->daily_capacity }}</td>
                    <td>{{ $partner->priority }}</td>
                    <td><x-ui.badge :tone="$partner->is_active ? 'success' : 'neutral'">{{ $partner->is_active ? __('Active') : __('Paused') }}</x-ui.badge></td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
