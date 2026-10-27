<x-layouts.shop :title="__('Artists')">
    <div class="container-page py-12">
        <x-ui.page-header :eyebrow="__('Independent creators')" :title="__('Meet the artists')" :description="__('Every purchase supports the artist who made the design.')">
            <x-slot:actions>
                <form method="get" class="flex gap-2">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Find an artist') }}" class="input w-56" aria-label="{{ __('Find an artist') }}">
                    <x-ui.button variant="secondary">{{ __('Search') }}</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.page-header>
        @if ($artists->isEmpty())
            <x-ui.empty icon="palette" :title="__('No artists found')" :description="__('Try another name.')" />
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($artists as $artist)
                    <x-shop.artist-card :artist="$artist" />
                @endforeach
            </div>
            <div class="mt-10">{{ $artists->links() }}</div>
        @endif
    </div>
</x-layouts.shop>
