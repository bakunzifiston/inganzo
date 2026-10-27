<x-layouts.dashboard :title="__('Promotions')" area="admin">
    <x-ui.page-header :title="__('Homepage promotions')" :description="__('Banners shown on the storefront while active and within their dates.')">
        <x-slot:actions><x-ui.button :href="route('admin.promotions.create')"><x-ui.icon name="plus" class="size-4" /> {{ __('New promotion') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-admin.table-card :rows="$promotions" :empty="__('No promotions.')">
        <thead><tr><th>{{ __('Title') }}</th><th>{{ __('Placement') }}</th><th>{{ __('Dates') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
        <tbody>
            @foreach ($promotions as $promotion)
                <tr>
                    <td><a href="{{ route('admin.promotions.edit', $promotion) }}" class="font-medium hover:text-brand-700">{{ $promotion->translate('title') }}</a></td>
                    <td>{{ $promotion->placement }} · {{ $promotion->theme }}</td>
                    <td class="text-xs text-muted">{{ $promotion->starts_at?->translatedFormat('j M Y') ?? '…' }} – {{ $promotion->ends_at?->translatedFormat('j M Y') ?? '…' }}</td>
                    <td><x-ui.badge :tone="$promotion->is_active ? 'success' : 'neutral'">{{ $promotion->is_active ? __('Active') : __('Off') }}</x-ui.badge></td>
                    <td class="text-right"><x-admin.inline-form :action="route('admin.promotions.destroy', $promotion)" method="delete" :label="__('Delete')" :confirm="__('Delete this promotion?')" /></td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
