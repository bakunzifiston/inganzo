@php
    $editing = $product !== null;
    $editable = ! $editing || $product->status->isEditable();
    $currency = config('marketplace.default_currency');
    $initialVariants = old('variants', $editing
        ? $product->variants->map(fn ($v) => [
            'id' => $v->id, 'color_name' => $v->color_name, 'color_hex' => $v->color_hex ?? '#8a5a2b', 'size_label' => $v->size_label, 'sku' => $v->sku,
            'price_amount' => $v->price_amount, 'sale_price_amount' => $v->sale_price_amount, 'stock_quantity' => $v->stock_quantity, 'is_active' => $v->is_active,
        ])->all()
        : [['id' => null, 'color_name' => '', 'color_hex' => '#8a5a2b', 'size_label' => '', 'sku' => '', 'price_amount' => null, 'sale_price_amount' => null, 'stock_quantity' => 1, 'is_active' => true]]);
    $value = fn (string $key, $default = null) => old($key, $editing ? $product->{$key} : $default);
    $title = $editing ? $product->title : __('New finished product');
@endphp
<x-layouts.dashboard :title="$title" area="studio">
    <div class="mb-6 text-sm text-muted"><a href="{{ route('studio.products.index', ['type' => 'finished']) }}" class="hover:text-ink">{{ __('Products') }}</a> / {{ $title }}</div>

    <x-ui.page-header :title="$title" :description="__('A complete physical product that you stock and ship yourself.')">
        @if ($editing)
            <x-slot:actions><x-ui.badge :status="$product->status" /></x-slot:actions>
        @endif
    </x-ui.page-header>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-danger-100 bg-danger-50 p-4 text-sm text-danger-700" role="alert">
            <p class="font-semibold">{{ __('Please fix the following:') }}</p>
            <ul class="mt-1 list-disc space-y-0.5 pl-5">@foreach (collect($errors->all())->unique()->take(8) as $message)<li>{{ $message }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($editing && $product->status === \App\Enums\FinishedProductStatus::Rejected && $product->moderation_note)
        <div class="mb-6 rounded-xl bg-danger-50 p-4 text-sm text-danger-700"><p class="font-semibold">{{ __('Reviewer feedback') }}</p><p class="mt-1">{{ $product->moderation_note }}</p></div>
    @endif
    @if ($editing && $product->status === \App\Enums\FinishedProductStatus::Suspended)
        <div class="mb-6 rounded-xl bg-danger-50 p-4 text-sm text-danger-700"><p class="font-semibold">{{ __('This product was taken down.') }}</p><p class="mt-1">{{ $product->moderation_note }}</p></div>
    @endif

    <form method="post" enctype="multipart/form-data" class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
        action="{{ $editing ? route('studio.finished.update', $product) : route('studio.finished.store') }}"
        x-data="{
            variants: @js($initialVariants),
            showPhotos: 0,
            add() { this.variants.push({ id: null, color_name: '', color_hex: '#8a5a2b', size_label: '', sku: '', price_amount: null, sale_price_amount: null, stock_quantity: 1, is_active: true }); },
            remove(i) { this.variants.length > 1 && this.variants.splice(i, 1); },
        }">
        @csrf
        @if ($editing) @method('put') @endif

        <div class="space-y-6">
            {{-- Basics --}}
            <x-ui.card :title="__('Product details')">
                <div class="space-y-5">
                    <x-form.input name="title" :label="__('Product name')" :value="$value('title')" required maxlength="120" :disabled="! $editable" />
                    <x-form.textarea name="description" :label="__('Description')" :value="$value('description')" rows="6" maxlength="5000" :hint="__('Materials, dimensions, care instructions, what is in the box.')" :disabled="! $editable" />
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.select name="category_id" :label="__('Category')" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->translate('name')])" :value="$value('category_id')" :placeholder="__('None')" :disabled="! $editable" />
                        <x-form.input name="sku" :label="__('Product SKU')" :value="$value('sku')" maxlength="50" :hint="__('Optional. Option SKUs are generated from it.')" :disabled="! $editable" />
                    </div>
                    <x-form.checkbox name="is_handmade" :label="__('This product is handmade')" :checked="(bool) $value('is_handmade', false)" />
                </div>
            </x-ui.card>

            {{-- Photos --}}
            <x-ui.card :title="__('Photos')" :description="__('Up to :max photos, PNG, JPEG or WebP, at least 400 px. The first photo is the cover.', ['max' => \App\Services\Inventory\FinishedProductService::MAX_IMAGES])">
                @if ($editing && $product->images->isNotEmpty())
                    <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($product->images as $image)
                            <div class="rounded-xl border border-line p-2">
                                <img src="{{ $image->thumbnailUrl() }}" alt="" class="aspect-square w-full rounded-lg bg-sand object-cover">
                                <label class="mt-2 flex items-center gap-1.5 text-xs"><input type="radio" name="cover_image_id" value="{{ $image->id }}" @checked($loop->first) class="accent-brand-600"> {{ __('Cover') }}</label>
                                <label class="mt-1 flex items-center gap-1.5 text-xs text-danger-700"><input type="checkbox" name="remove_images[]" value="{{ $image->id }}" class="accent-danger-700"> {{ __('Remove') }}</label>
                            </div>
                        @endforeach
                    </div>
                @endif
                <label class="flex cursor-pointer flex-col items-center rounded-2xl border-2 border-dashed border-line-strong px-4 py-8 text-center transition hover:border-brand-600">
                    <x-ui.icon name="image" class="size-7 text-brand-700" />
                    <span class="mt-2 text-sm font-semibold">{{ __('Choose photos') }}</span>
                    <span class="text-xs text-muted" x-text="showPhotos ? showPhotos + ' {{ __('selected') }}' : '{{ __('Hold ⌘ or Ctrl to pick several') }}'"></span>
                    <input type="file" name="images[]" multiple accept="image/png,image/jpeg,image/webp" class="sr-only" @change="showPhotos = $event.target.files.length" @disabled(! $editable)>
                </label>
                @error('images')<p class="mt-2 text-sm text-danger-700">{{ $message }}</p>@enderror
                @error('images.*')<p class="mt-2 text-sm text-danger-700">{{ $message }}</p>@enderror
            </x-ui.card>

            {{-- Options & stock --}}
            <x-ui.card :title="__('Options and stock')" :description="__('Add a row for every colour and size you sell. Each has its own SKU and stock; leave colour and size empty if the product has no options.')">
                <div class="space-y-3">
                    <template x-for="(variant, i) in variants" :key="i">
                        <div class="rounded-xl border border-line p-3" :class="! variant.is_active && 'bg-paper opacity-75'">
                            <input type="hidden" :name="`variants[${i}][id]`" :value="variant.id">
                            <div class="grid gap-3 sm:grid-cols-6">
                                <label class="sm:col-span-2"><span class="studio-label">{{ __('Colour') }}</span>
                                    <span class="flex gap-1.5">
                                        <input type="color" class="h-9 w-10 shrink-0 cursor-pointer rounded-md border border-line-strong" :name="`variants[${i}][color_hex]`" x-model="variant.color_hex" aria-label="{{ __('Colour swatch') }}">
                                        <input type="text" class="input h-9 py-1.5" maxlength="50" :name="`variants[${i}][color_name]`" x-model="variant.color_name" placeholder="{{ __('e.g. Terracotta') }}">
                                    </span></label>
                                <label><span class="studio-label">{{ __('Size') }}</span><input type="text" class="input h-9 py-1.5" maxlength="20" :name="`variants[${i}][size_label]`" x-model="variant.size_label" placeholder="{{ __('e.g. M or 30 cm') }}"></label>
                                <label><span class="studio-label">{{ __('SKU') }}</span><input type="text" class="input h-9 py-1.5 font-mono" maxlength="64" :name="`variants[${i}][sku]`" x-model="variant.sku" placeholder="{{ __('Auto') }}"></label>
                                <label><span class="studio-label">{{ __('Stock') }}</span><input type="number" min="0" max="100000" class="input h-9 py-1.5" :name="`variants[${i}][stock_quantity]`" x-model.number="variant.stock_quantity" required></label>
                                <div class="flex items-end justify-between gap-2">
                                    <label class="flex items-center gap-1.5 pb-2 text-xs"><input type="checkbox" class="accent-brand-600" :name="`variants[${i}][is_active]`" value="1" x-model="variant.is_active"> {{ __('Available') }}</label>
                                    <button type="button" class="studio-btn mb-0.5 text-danger-700" @click="remove(i)" x-show="variants.length > 1" aria-label="{{ __('Remove option') }}"><x-ui.icon name="trash" class="size-4" /></button>
                                </div>
                            </div>
                            <div class="mt-3 grid gap-3 sm:grid-cols-6">
                                <label class="sm:col-span-2"><span class="studio-label">{{ __('Price for this option (:c)', ['c' => $currency]) }}</span><input type="number" min="1" class="input h-9 py-1.5" :name="`variants[${i}][price_amount]`" x-model.number="variant.price_amount" placeholder="{{ __('Same as product') }}"></label>
                                <label class="sm:col-span-2"><span class="studio-label">{{ __('Sale price (:c)', ['c' => $currency]) }}</span><input type="number" min="1" class="input h-9 py-1.5" :name="`variants[${i}][sale_price_amount]`" x-model.number="variant.sale_price_amount" placeholder="{{ __('Optional') }}"></label>
                            </div>
                        </div>
                    </template>
                </div>
                <button type="button" class="mt-4 inline-flex items-center gap-1.5 rounded-full border border-line-strong px-4 py-2 text-sm font-semibold hover:border-brand-700" @click="add()" x-show="variants.length < 60"><x-ui.icon name="plus" class="size-4" /> {{ __('Add option') }}</button>
                @error('variants')<p class="mt-2 text-sm text-danger-700">{{ $message }}</p>@enderror
                <p class="mt-3 text-xs text-subtle">{{ __('Options that already appear in orders are switched off instead of deleted when you remove them.') }}</p>
            </x-ui.card>

            {{-- Shipping --}}
            <x-ui.card :title="__('Shipping')" :description="__('You pack and ship ready-made products yourself. Delivery fees are charged by the marketplace using its normal delivery zones.')">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="processing_days" type="number" min="0" max="60" :label="__('Days to prepare an order')" :value="$value('processing_days', 2)" required />
                    <x-form.input name="weight_grams" type="number" min="1" :label="__('Weight (grams)')" :value="$value('weight_grams')" />
                    <x-form.input name="ships_from" :label="__('Ships from')" :value="$value('ships_from')" maxlength="80" :hint="__('Town or district, e.g. Kigali – Gasabo')" />
                    <x-form.input name="shipping_note" :label="__('Shipping note')" :value="$value('shipping_note')" maxlength="255" :hint="__('Shown to customers, e.g. fragile, packed in a box.')" />
                </div>
            </x-ui.card>
        </div>

        <aside class="space-y-6 xl:sticky xl:top-24">
            <x-ui.card :title="__('Price')">
                <div class="space-y-5">
                    <x-form.input name="price_amount" type="number" min="1" :label="__('Price (:c)', ['c' => $currency])" :value="$value('price_amount')" required :hint="__('Includes VAT. Whole francs.')" />
                    <x-form.input name="sale_price_amount" type="number" min="1" :label="__('Sale price (:c)', ['c' => $currency])" :value="$value('sale_price_amount')" :hint="__('Optional. Must be lower than the price; customers see the original struck through.')" />
                    <x-form.input name="low_stock_threshold" type="number" min="0" :label="__('Low-stock alert at')" :value="$value('low_stock_threshold', 3)" required :hint="__('You are warned when total stock falls to this number.')" />
                    <p class="rounded-lg bg-sand p-3 text-xs text-muted">{{ __('The marketplace keeps :percent% of each sale; you earn the rest. Earnings are released after delivery.', ['percent' => rtrim(rtrim(number_format(app(\App\Services\Settings::class)->get('marketplace.finished_commission_bps', 1000) / 100, 1), '0'), '.')]) }}</p>
                </div>
            </x-ui.card>

            <div class="card space-y-3 p-5">
                @if ($editable)
                    <x-ui.button name="intent" value="save" variant="secondary" class="w-full">{{ __('Save') }}</x-ui.button>
                    @if (! $editing || in_array($product->status, [\App\Enums\FinishedProductStatus::Draft, \App\Enums\FinishedProductStatus::Rejected, \App\Enums\FinishedProductStatus::Archived], true))
                        <x-ui.button name="intent" value="publish" variant="accent" class="w-full">{{ app(\App\Services\Inventory\FinishedProductService::class)->requiresApproval() ? __('Save and submit for review') : __('Save and publish') }}</x-ui.button>
                    @endif
                @endif
                @if ($editing && $product->status === \App\Enums\FinishedProductStatus::Active)
                    <x-ui.button :href="route('finished.show', $product)" variant="secondary" class="w-full" type="button">{{ __('View in shop') }}</x-ui.button>
                @endif
                @error('publish')<ul class="space-y-1 text-xs text-danger-700">@foreach ($errors->get('publish') as $message)<li>• {{ is_array($message) ? implode(' ', $message) : $message }}</li>@endforeach</ul>@enderror
                @if ($editing && $product->status === \App\Enums\FinishedProductStatus::PendingReview)<p class="text-xs text-muted">{{ __('Our team is reviewing this product. This usually takes 1–2 business days.') }}</p>@endif
            </div>
        </aside>
    </form>

    @if ($editing)
        <div class="mt-6 max-w-3xl">
            @if ($product->status === \App\Enums\FinishedProductStatus::Archived)
                <form method="post" action="{{ route('studio.finished.restore', $product) }}">@csrf<x-ui.button variant="secondary">{{ __('Restore product') }}</x-ui.button></form>
            @elseif ($product->status !== \App\Enums\FinishedProductStatus::Suspended)
                <form method="post" action="{{ route('studio.finished.archive', $product) }}" onsubmit="return confirm('{{ __('Archive this product? It will be removed from your shop. Existing orders are not affected.') }}')">@csrf<button class="text-xs text-muted underline hover:text-danger-700">{{ __('Archive product') }}</button></form>
            @endif
        </div>
    @endif
</x-layouts.dashboard>
