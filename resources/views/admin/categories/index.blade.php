<x-layouts.dashboard :title="__('Categories')" area="admin">
    <x-ui.page-header :title="__('Design categories')" :description="__('Themes artists choose for their designs. Featured categories appear on the homepage.')">
        <x-slot:actions><x-ui.button :href="route('admin.categories.create')"><x-ui.icon name="plus" class="size-4" /> {{ __('New category') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <x-admin.table-card :rows="$categories">
        <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Parent') }}</th><th class="text-right">{{ __('Designs') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @foreach ($categories as $category)
                <tr><td><a href="{{ route('admin.categories.edit', $category) }}" class="font-medium hover:text-brand-700">{{ $category->translate('name') }}</a><p class="text-xs text-muted">{{ $category->name['rw'] ?? '' }} · {{ $category->slug }}</p></td><td class="text-muted">{{ $category->parent?->translate('name') ?? '—' }}</td><td class="text-right">{{ $category->artworks_count }}</td><td class="space-x-1"><x-ui.badge :tone="$category->is_active ? 'success' : 'neutral'">{{ $category->is_active ? __('Active') : __('Hidden') }}</x-ui.badge>@if ($category->is_featured)<x-ui.badge tone="accent">{{ __('Featured') }}</x-ui.badge>@endif</td></tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
