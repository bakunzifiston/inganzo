<x-layouts.dashboard :title="__('Collections')" area="admin">
    <x-ui.page-header :title="__('Curated collections')" :description="__('Featured collections appear on the homepage. Only published designs can be added.')">
        <x-slot:actions><x-ui.button :href="route('admin.collections.create')"><x-ui.icon name="plus" class="size-4" /> {{ __('New collection') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-admin.table-card :rows="$collections" :empty="__('No collections yet.')">
        <thead><tr><th>{{ __('Collection') }}</th><th class="text-right">{{ __('Designs') }}</th><th>{{ __('Position') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @foreach ($collections as $collection)
                <tr><td><a href="{{ route('admin.collections.edit', $collection) }}" class="font-medium hover:text-brand-700">{{ $collection->translate('title') }}</a><p class="text-xs text-muted">/collections/{{ $collection->slug }}</p></td><td class="text-right">{{ $collection->artworks_count }}</td><td>{{ $collection->position }}</td><td class="space-x-1"><x-ui.badge :tone="$collection->is_active ? 'success' : 'neutral'">{{ $collection->is_active ? __('Active') : __('Hidden') }}</x-ui.badge>@if ($collection->is_featured)<x-ui.badge tone="accent">{{ __('Featured') }}</x-ui.badge>@endif</td></tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
