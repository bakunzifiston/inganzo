<div class="grid gap-8 lg:grid-cols-[240px_1fr]" x-data="{ filtersOpen: false }">
    {{-- Filters --}}
    <aside class="lg:sticky lg:top-24 lg:self-start">
        <button type="button" class="mb-4 flex w-full items-center justify-between rounded-xl border border-line bg-white px-4 py-3 text-sm font-semibold lg:hidden" @click="filtersOpen = ! filtersOpen" :aria-expanded="filtersOpen">
            <span class="flex items-center gap-2"><x-ui.icon name="filter" class="size-4" /> {{ __('Filters') }}</span>
            <x-ui.icon name="chevron-down" class="size-4 transition" ::class="filtersOpen && 'rotate-180'" />
        </button>
        <div class="space-y-7 lg:block" :class="filtersOpen ? 'block' : 'hidden'">
            <div>
                <label for="filter-q" class="label">{{ __('Search') }}</label>
                <input id="filter-q" type="search" wire:model.live.debounce.400ms="q" placeholder="{{ __('Designs, products, artists') }}" class="input">
            </div>

            <fieldset>
                <legend class="label">{{ __('Product') }}</legend>
                <div class="space-y-1">
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-sand">
                        <input type="radio" wire:model.live="type" value="" class="accent-brand-600"> {{ __('All products') }}
                    </label>
                    <label wire:key="type-ready-made" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-sand">
                        <input type="radio" wire:model.live="type" value="{{ \App\Services\Catalog\CatalogSearch::READY_MADE }}" class="accent-brand-600"> {{ __('Ready-made') }}
                    </label>
                    @foreach ($types as $productType)
                        <label wire:key="type-{{ $productType->id }}" class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-sand">
                            <input type="radio" wire:model.live="type" value="{{ $productType->slug }}" class="accent-brand-600"> {{ $productType->translate('name') }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            @unless ($lockCategory)
                <div>
                    <label for="filter-category" class="label">{{ __('Category') }}</label>
                    <select id="filter-category" wire:model.live="category" class="input">
                        <option value="">{{ __('All categories') }}</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->slug }}" wire:key="cat-{{ $cat->id }}">{{ $cat->translate('name') }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless

            <fieldset>
                <legend class="label">{{ __('Colour') }}</legend>
                <div class="grid grid-cols-2 gap-1.5">
                    @foreach ($colorOptions as $option)
                        @php $active = $color === $option['name']; @endphp
                        <button type="button" wire:key="color-{{ $loop->index }}" wire:click="$set('color', @js($active ? '' : $option['name']))"
                            aria-pressed="{{ $active ? 'true' : 'false' }}"
                            @class(['flex items-center gap-2 rounded-lg border px-2 py-1.5 text-left text-xs font-medium transition',
                                'border-brand-600 bg-brand-600 text-ink' => $active,
                                'border-line-strong bg-white hover:border-ink' => ! $active])>
                            <span class="size-4 shrink-0 rounded-full ring-1 ring-black/25 ring-inset" style="background: {{ $option['hex'] }}" aria-hidden="true"></span>
                            <span class="truncate">{{ $option['name'] }}</span>
                            @if ($active)<x-ui.icon name="check" class="ml-auto size-3.5 shrink-0" />@endif
                        </button>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="label">{{ __('Size') }}</legend>
                <div class="space-y-3">
                    @foreach ($sizeGroups as $group => $labels)
                        <div wire:key="size-group-{{ $loop->index }}">
                            <p class="mb-1.5 text-xs text-muted">{{ $group }}</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($labels as $label)
                                    <button type="button" wire:key="size-{{ $group }}-{{ $loop->index }}" wire:click="$set('size', @js($size === $label ? '' : $label))"
                                        aria-pressed="{{ $size === $label ? 'true' : 'false' }}"
                                        @class(['min-w-10 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition', 'border-brand-600 bg-brand-600 text-ink' => $size === $label, 'border-line-strong bg-white hover:border-ink' => $size !== $label])>{{ $label }}</button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="label">{{ __('Price (RWF)') }}</legend>
                <div class="flex items-center gap-2">
                    <input type="number" min="0" step="500" wire:model.live.debounce.600ms="min" placeholder="{{ __('Min') }}" class="input" aria-label="{{ __('Minimum price') }}">
                    <span class="text-muted">–</span>
                    <input type="number" min="0" step="500" wire:model.live.debounce.600ms="max" placeholder="{{ __('Max') }}" class="input" aria-label="{{ __('Maximum price') }}">
                </div>
            </fieldset>

            @if ($hasFilters)
                <button type="button" wire:click="clearFilters" class="link text-sm">{{ __('Clear all filters') }}</button>
            @endif
        </div>
    </aside>

    {{-- Results --}}
    <div>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-muted" aria-live="polite">
                {{ trans_choice(':count product|:count products', $results->total(), ['count' => number_format($results->total())]) }}
                <span wire:loading class="ml-2 text-subtle">{{ __('Updating…') }}</span>
            </p>
            <label class="flex items-center gap-2 text-sm">
                <span class="text-muted">{{ __('Sort by') }}</span>
                <select wire:model.live="sort" class="rounded-lg border border-line-strong bg-white py-1.5 pr-8 pl-3 text-sm">
                    <option value="trending">{{ __('Trending') }}</option>
                    <option value="newest">{{ __('Newest') }}</option>
                    <option value="price_asc">{{ __('Price: low to high') }}</option>
                    <option value="price_desc">{{ __('Price: high to low') }}</option>
                </select>
            </label>
        </div>

        @if ($results->isEmpty())
            <x-ui.empty icon="search" :title="__('No products match')" :description="__('Try a different search or remove some filters.')">
                @if ($hasFilters)<x-ui.button type="button" variant="secondary" wire:click="clearFilters">{{ __('Clear filters') }}</x-ui.button>@endif
            </x-ui.empty>
        @else
            <div class="grid grid-cols-2 gap-x-4 gap-y-8 sm:grid-cols-3 xl:grid-cols-4" wire:loading.class="opacity-60">
                @foreach ($results as $item)
                    <x-shop.product-card :item="$item" wire:key="{{ $item instanceof \App\Models\FinishedProduct ? 'fp' : 'ap' }}-{{ $item->id }}" />
                @endforeach
            </div>
            <div class="mt-10">{{ $results->links() }}</div>
        @endif
    </div>
</div>
