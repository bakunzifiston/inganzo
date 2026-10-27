<x-layouts.dashboard :title="__('Favourites')" area="account">
    <x-ui.page-header :title="__('Favourite designs')" :description="__('Designs you saved while browsing.')" />
    @if ($items->isEmpty())
        <x-ui.empty icon="heart" :title="__('No favourites yet')" :description="__('Tap “Save to favourites” on any product page to keep it here.')">
            <x-ui.button :href="route('shop.index')">{{ __('Browse designs') }}</x-ui.button>
        </x-ui.empty>
    @else
        <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 xl:grid-cols-4">
            @foreach ($items as $item)
                <div>
                    <x-shop.product-card :item="$item" />
                    <form method="post" action="{{ route('favorites.toggle', $item->artwork) }}" class="mt-2">
                        @csrf
                        <button class="inline-flex items-center gap-1 text-xs text-muted hover:text-danger-700"><x-ui.icon name="heart" class="size-3.5 fill-brand-600 text-brand-700" /> {{ __('Remove') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.dashboard>
