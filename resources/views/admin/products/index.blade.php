<x-layouts.dashboard :title="__('Products')" area="admin">
    <x-ui.page-header :title="__('Product catalogue')" :description="__('Blanks that artists can put their designs on. Base cost is what production costs the platform per unit before markups.')">
        <x-slot:actions><x-ui.button :href="route('admin.products.create')"><x-ui.icon name="plus" class="size-4" /> {{ __('New product') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($products as $product)
            <a href="{{ route('admin.products.edit', $product) }}" class="card flex gap-4 p-4 transition hover:shadow-[var(--shadow-lift)]">
                <div class="aspect-square w-24 shrink-0 rounded-xl bg-sand"><x-shop.mockup :product="$product" :color="$product->colors->first()" class="h-full w-full p-1" /></div>
                <div class="min-w-0 flex-1 text-sm">
                    <div class="flex items-start justify-between gap-2"><p class="font-semibold">{{ $product->translate('name') }}</p><x-ui.badge :tone="$product->is_active ? 'success' : 'neutral'">{{ $product->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></div>
                    <p class="text-xs text-muted">{{ $product->type->translate('name') }} · {{ $product->slug }}</p>
                    <p class="mt-2 font-medium"><x-ui.money :amount="$product->base_cost_amount" :currency="$product->currency" /> <span class="text-xs font-normal text-muted">{{ __('base cost') }}</span></p>
                    <p class="mt-1 text-xs text-muted">{{ trans_choice(':count colour|:count colours', $product->colors->count(), ['count' => $product->colors->count()]) }} · {{ trans_choice(':count variant|:count variants', $product->variants_count, ['count' => $product->variants_count]) }} · {{ trans_choice(':count partner|:count partners', $product->partner_services_count, ['count' => $product->partner_services_count]) }}</p>
                    @if ($product->partner_services_count === 0)<p class="mt-1 text-xs text-warning-700">{{ __('No printing partner offers this product.') }}</p>@endif
                </div>
            </a>
        @endforeach
    </div>
</x-layouts.dashboard>
