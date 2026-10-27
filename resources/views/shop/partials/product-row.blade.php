@if ($items->isNotEmpty())
    <section class="container-page py-12">
        <div class="mb-7 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold sm:text-3xl">{{ $title }}</h2>
                @if ($subtitle)<p class="mt-1 text-sm text-muted">{{ $subtitle }}</p>@endif
            </div>
            <a href="{{ $link }}" class="link shrink-0 text-sm">{{ __('See all') }}</a>
        </div>
        <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($items->take(8) as $item)
                <x-shop.product-card :item="$item" />
            @endforeach
        </div>
    </section>
@endif
