<x-layouts.dashboard :title="__('Ready-made products')" area="admin">
    <x-ui.page-header :title="__('Ready-made products')" :description="__('Products artists sell from their own stock. Check that they are real, lawful, accurately described and fairly priced before they go on sale.')">
        <x-slot:actions>
            <form method="get" class="flex gap-2"><input type="hidden" name="status" value="{{ $status }}"><input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search titles') }}" class="input w-48"></form>
        </x-slot:actions>
    </x-ui.page-header>
    <div class="mb-6 flex flex-wrap gap-2 text-sm">
        @foreach (\App\Enums\FinishedProductStatus::cases() as $s)
            <a href="{{ route('admin.finished.index', ['status' => $s->value]) }}" @class(['rounded-full px-3 py-1.5 font-medium', 'bg-brand-600 text-ink' => $status === $s->value, 'bg-white ring-1 ring-line' => $status !== $s->value])>{{ $s->label() }} <span class="opacity-60">{{ $counts[$s->value] ?? 0 }}</span></a>
        @endforeach
    </div>
    @if ($products->isEmpty())
        <x-ui.empty icon="check-circle" :title="__('Nothing here')" :description="$status === 'pending_review' ? __('The review queue is empty.') : __('No products with this status.')" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($products as $product)
                <a href="{{ route('admin.finished.show', $product) }}" class="card group overflow-hidden transition hover:shadow-[var(--shadow-lift)]">
                    <div class="aspect-square bg-sand"><x-shop.finished-thumb :product="$product" class="h-full w-full" /></div>
                    <div class="p-4">
                        <p class="truncate font-semibold group-hover:text-brand-700">{{ $product->title }}</p>
                        <p class="truncate text-xs text-muted">{{ $product->artist->display_name }} · {{ $product->category?->translate('name') ?? __('No category') }}</p>
                        <div class="mt-2 flex items-center justify-between text-xs">
                            <x-ui.badge :status="$product->artist->status" />
                            <span class="font-medium"><x-ui.money :amount="$product->price_from_amount" :currency="$product->currency" /></span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $products->links() }}</div>
    @endif
</x-layouts.dashboard>
