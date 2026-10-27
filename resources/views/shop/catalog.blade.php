<x-layouts.shop :title="$heading">
    <div class="border-b border-line bg-white">
        <div class="container-page py-10">
            <nav class="mb-3 text-xs text-muted" aria-label="{{ __('Breadcrumb') }}">
                <a href="{{ route('home') }}" class="hover:text-ink">{{ __('Home') }}</a> /
                <a href="{{ route('shop.index') }}" class="hover:text-ink">{{ __('Shop') }}</a>
            </nav>
            <h1 class="text-3xl font-bold sm:text-4xl">{{ $heading }}</h1>
            @if ($category?->description)<p class="mt-2 max-w-2xl text-muted">{{ $category->translate('description') }}</p>@endif
            @if ($collection?->description)<p class="mt-2 max-w-2xl text-muted">{{ $collection->translate('description') }}</p>@endif
        </div>
    </div>
    <div class="container-page py-10">
        <livewire:catalog-browser :preset-category="$category?->slug" :preset-collection="$collection?->id" />
    </div>
</x-layouts.shop>
