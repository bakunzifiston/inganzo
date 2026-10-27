<x-layouts.dashboard :title="__('Product types')" area="admin">
    <x-ui.page-header :title="__('Product types')" :description="__('Merchandise groups used for navigation and filters.')">
        <x-slot:actions><x-ui.button :href="route('admin.product-types.create')"><x-ui.icon name="plus" class="size-4" /> {{ __('New type') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-admin.table-card :rows="$types">
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Slug') }}</th><th class="text-right">{{ __('Products') }}</th><th>{{ __('Position') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @foreach ($types as $type)
                <tr><td><a href="{{ route('admin.product-types.edit', $type) }}" class="font-medium hover:text-brand-700">{{ $type->translate('name') }}</a><p class="text-xs text-muted">{{ $type->name['rw'] ?? '' }}</p></td><td class="text-muted">{{ $type->slug }}</td><td class="text-right">{{ $type->products_count }}</td><td>{{ $type->position }}</td><td><x-ui.badge :tone="$type->is_active ? 'success' : 'neutral'">{{ $type->is_active ? __('Active') : __('Hidden') }}</x-ui.badge></td></tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
