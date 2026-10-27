<x-layouts.dashboard :title="__('Artists')" area="admin">
    <x-ui.page-header :title="__('Artists')" :description="__('Approve new artists before their designs can go live. Suspended artists are hidden from the storefront.')" />
    <x-admin.table-card :rows="$artists" :empty="__('No artists found.')">
        <x-slot:toolbar>
            <form method="get" class="flex flex-wrap items-end gap-3">
                <label class="block text-xs font-medium text-muted">{{ __('Search') }}<input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Name or e-mail') }}" class="mt-1 block w-56 rounded-lg border border-line-strong px-3 py-1.5 text-sm text-ink"></label>
                <x-admin.filter name="status" :label="__('Status')" :options="\App\Enums\ArtistStatus::options()" :value="request('status')" />
                <x-ui.button size="sm">{{ __('Apply') }}</x-ui.button>
            </form>
        </x-slot:toolbar>
        <thead><tr><th>{{ __('Artist') }}</th><th>{{ __('Status') }}</th><th class="text-right">{{ __('Designs') }}</th><th>{{ __('Joined') }}</th></tr></thead>
        <tbody>
            @foreach ($artists as $artist)
                <tr>
                    <td><a href="{{ route('admin.artists.show', $artist) }}" class="flex items-center gap-3"><x-shop.avatar :artist="$artist" class="size-9 text-sm" /><span><span class="block font-medium hover:text-brand-700">{{ $artist->display_name }} @if ($artist->is_featured)<span class="text-warning-700">★</span>@endif</span><span class="text-xs text-muted">{{ $artist->user->email }}</span></span></a></td>
                    <td><x-ui.badge :status="$artist->status" /></td>
                    <td class="text-right tabular-nums">{{ $artist->published_artworks_count }} / {{ $artist->artworks_count }}</td>
                    <td class="text-muted">{{ $artist->created_at->translatedFormat('j M Y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
