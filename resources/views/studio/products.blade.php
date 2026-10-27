@php
    $filterUrl = fn (array $overrides) => route('studio.products.index', array_filter([...request()->only(['type', 'status', 'stock', 'q']), ...$overrides, 'page' => null], fn ($v) => $v !== null && $v !== ''));
    $pill = fn (bool $on) => $on ? 'bg-brand-600 text-ink' : 'bg-white ring-1 ring-line hover:ring-ink';
    $total = $counts->sum();
@endphp
<x-layouts.dashboard :title="__('Products')" area="studio">
    <x-ui.page-header :title="__('Products')" :description="__('Your printed designs and ready-made products in one place.')">
        <x-slot:actions>
            <x-ui.button :href="route('studio.sell')" variant="accent"><x-ui.icon name="plus" class="size-4" /> {{ __('Add product') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-3 flex flex-wrap items-center gap-2" role="group" aria-label="{{ __('Product type') }}">
        <a href="{{ $filterUrl(['type' => null]) }}" @class(['rounded-full px-3.5 py-1.5 text-sm font-medium', $pill($filters['type'] === '')])>{{ __('All') }} <span class="opacity-60">{{ $total }}</span></a>
        @foreach (\App\Enums\SellingType::cases() as $case)
            <a href="{{ $filterUrl(['type' => $case->value]) }}" @class(['rounded-full px-3.5 py-1.5 text-sm font-medium', $pill($filters['type'] === $case->value)])>{{ $case->label() }} <span class="opacity-60">{{ $counts[$case->value] ?? 0 }}</span></a>
        @endforeach
        <form method="get" class="ml-auto flex gap-2">
            @foreach (['type', 'status', 'stock'] as $keep)@if ($filters[$keep] !== '')<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif @endforeach
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="{{ __('Search titles') }}" class="input w-56" aria-label="{{ __('Search titles') }}">
        </form>
    </div>
    <div class="mb-6 flex flex-wrap items-center gap-2 text-xs">
        <span class="text-muted">{{ __('Status') }}</span>
        @foreach (['live' => __('On sale'), 'draft' => __('Draft'), 'review' => __('In review'), 'changes' => __('Changes requested'), 'taken_down' => __('Taken down'), 'archived' => __('Archived')] as $key => $label)
            <a href="{{ $filterUrl(['status' => $filters['status'] === $key ? null : $key]) }}" @class(['rounded-full px-3 py-1 font-medium', $pill($filters['status'] === $key)])>{{ $label }}</a>
        @endforeach
        <span class="ml-3 text-muted">{{ __('Stock') }}</span>
        @foreach (['in' => __('In stock'), 'low' => __('Low stock'), 'out' => __('Out of stock')] as $key => $label)
            <a href="{{ $filterUrl(['stock' => $filters['stock'] === $key ? null : $key]) }}" @class(['rounded-full px-3 py-1 font-medium', $pill($filters['stock'] === $key)])>{{ $label }} <span class="opacity-60">{{ $stockCounts[$key] }}</span></a>
        @endforeach
    </div>

    @if ($rows->isEmpty())
        <x-ui.empty icon="package" :title="$total ? __('No products match') : __('No products yet')" :description="$total ? __('Try removing a filter.') : __('Sell a printed design or a ready-made product — or both.')">
            <x-ui.button :href="route('studio.sell')" variant="accent">{{ __('Add your first product') }}</x-ui.button>
        </x-ui.empty>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($rows as $row)
                @if ($row->type === \App\Enums\SellingType::Finished)
                    @php $product = $row->model; $stock = $product->totalStock(); $cover = $product->coverImage(); @endphp
                    <a href="{{ route('studio.finished.edit', $product) }}" class="group card overflow-hidden transition hover:shadow-[var(--shadow-lift)]">
                        <div class="relative aspect-[4/3] bg-sand">
                            @if ($cover)<img src="{{ $cover->thumbnailUrl() }}" alt="{{ $product->title }}" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
                            @else<div class="grid h-full place-items-center text-sm text-muted">{{ __('No photo yet') }}</div>@endif
                            <x-ui.badge :status="$row->type" class="absolute top-3 left-3 bg-white/95" />
                        </div>
                        <div class="space-y-1.5 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="truncate font-semibold">{{ $product->title }}</p>
                                <x-ui.badge :status="$product->status" />
                            </div>
                            <p class="text-xs text-muted"><x-ui.money :amount="$product->price_from_amount" :currency="$product->currency" /> · {{ trans_choice(':count option|:count options', $product->variants->where('is_active', true)->count(), ['count' => $product->variants->where('is_active', true)->count()]) }}</p>
                            <p @class(['text-xs font-medium', 'text-danger-700' => $stock === 0, 'text-warning-700' => $product->isLowStock(), 'text-success-700' => $stock > 0 && ! $product->isLowStock()])>
                                {{ $stock === 0 ? __('Out of stock') : trans_choice(':count in stock|:count in stock', $stock, ['count' => $stock]) }}@if ($product->isLowStock()) · {{ __('Low stock') }}@endif
                            </p>
                        </div>
                    </a>
                @else
                    @php $artwork = $row->model; @endphp
                    <a href="{{ route('studio.artworks.show', $artwork) }}" class="group card overflow-hidden transition hover:shadow-[var(--shadow-lift)]">
                        <div class="relative aspect-[4/3] bg-checker p-6">
                            @if ($artwork->currentVersion?->isReady())<img src="{{ $artwork->currentVersion->thumbnailUrl() }}" alt="{{ $artwork->title }}" class="h-full w-full object-contain transition group-hover:scale-105" loading="lazy">
                            @elseif (! $artwork->current_version_id)<div class="grid h-full place-items-center text-sm text-muted">{{ __('Not exported yet') }}</div>
                            @else<div class="grid h-full place-items-center text-sm text-muted">{{ __('Processing…') }}</div>@endif
                            <x-ui.badge :status="$row->type" class="absolute top-3 left-3 bg-white/95" />
                        </div>
                        <div class="space-y-1.5 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <p class="truncate font-semibold">{{ $artwork->title }}</p>
                                <x-ui.badge :status="$artwork->status" />
                            </div>
                            <p class="text-xs text-muted">{{ trans_choice(':count product|:count products', $artwork->enabled_products_count, ['count' => $artwork->enabled_products_count]) }} · {{ __('Printed on demand') }}</p>
                        </div>
                    </a>
                @endif
            @endforeach
        </div>
        <div class="mt-8">{{ $rows->links() }}</div>
    @endif
</x-layouts.dashboard>
