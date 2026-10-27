@php $editing = $product->exists; @endphp
<x-layouts.dashboard :title="$editing ? $product->translate('name') : __('New product')" area="admin">
    <x-ui.page-header :title="$editing ? $product->translate('name') : __('New product')" :description="__('Changes to cost or variants affect new prices only. Placed orders keep their frozen specification and price.')" />

    <div class="grid gap-6 xl:grid-cols-[1fr_320px]">
        <div class="space-y-6">
            <x-ui.card :title="__('Details')">
                <form method="post" action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}" class="grid gap-5 sm:grid-cols-2">
                    @csrf @if ($editing) @method('put') @endif
                    <x-form.select name="product_type_id" :label="__('Product type')" :options="$types" :value="$product->product_type_id" required />
                    <x-form.input name="slug" :label="__('Slug')" :value="$product->slug" required :hint="__('Used in URLs, e.g. classic-tee')" />
                    <x-form.input name="name_en" :label="__('Name (English)')" :value="$product->name['en'] ?? null" required />
                    <x-form.input name="name_rw" :label="__('Name (Kinyarwanda)')" :value="$product->name['rw'] ?? null" />
                    <x-form.textarea name="description_en" :label="__('Description (English)')" :value="$product->description['en'] ?? null" rows="3" />
                    <x-form.textarea name="description_rw" :label="__('Description (Kinyarwanda)')" :value="$product->description['rw'] ?? null" rows="3" />
                    <x-form.input name="base_cost" :label="__('Base cost')" :value="\App\Support\Money::toMajor($product->base_cost_amount, $product->currency ?? 'RWF')" inputmode="decimal" required />
                    <x-form.select name="currency" :label="__('Currency')" :options="$currencies" :value="$product->currency" />
                    <x-form.input name="production_days" type="number" :label="__('Production days')" :value="$product->production_days" required />
                    <x-form.input name="weight_grams" type="number" :label="__('Shipping weight (g)')" :value="$product->weight_grams" required />
                    <x-form.select name="mockup_template" :label="__('Mockup template')" :options="$templates" :value="$product->mockup_template" required />
                    <x-form.input name="position" type="number" :label="__('Sort position')" :value="$product->position" />
                    @unless ($editing)
                        <x-form.input name="area_width_mm" type="number" :label="__('Front print width (mm)')" value="300" required />
                        <x-form.input name="area_height_mm" type="number" :label="__('Front print height (mm)')" value="400" required />
                    @endunless
                    <div class="flex gap-6 sm:col-span-2">
                        <x-form.checkbox name="is_active" :label="__('Active (offered to artists and customers)')" :checked="$product->is_active" />
                        <x-form.checkbox name="is_shippable" :label="__('Shippable')" :checked="$product->is_shippable" />
                    </div>
                    <div class="sm:col-span-2"><x-ui.button>{{ $editing ? __('Save product') : __('Create product') }}</x-ui.button></div>
                </form>
            </x-ui.card>

            @if ($editing)
                <x-ui.card :title="__('Colours, sizes & variants')" :description="__('Each colour × size combination is a variant with its own SKU. Extra cost is added to the base cost.')">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <form method="post" action="{{ route('admin.products.colors.store', $product) }}" class="rounded-xl border border-line p-4">
                            @csrf
                            <p class="text-sm font-semibold">{{ __('Add colour') }}</p>
                            <div class="mt-2 flex gap-2"><input name="name" required placeholder="{{ __('Name') }}" class="input"><input name="hex" type="color" value="#FFFFFF" class="h-10 w-14 rounded-lg border border-line-strong"></div>
                            <x-form.checkbox name="is_dark" :label="__('Dark garment')" class="mt-2" />
                            <x-ui.button size="sm" variant="secondary" class="mt-2">{{ __('Add') }}</x-ui.button>
                        </form>
                        <form method="post" action="{{ route('admin.products.sizes.store', $product) }}" class="rounded-xl border border-line p-4">
                            @csrf
                            <p class="text-sm font-semibold">{{ __('Add size') }}</p>
                            <div class="mt-2 flex gap-2"><input name="label" required placeholder="{{ __('e.g. 3XL') }}" class="input"><input name="extra_cost" placeholder="{{ __('Extra cost') }}" inputmode="decimal" class="input"></div>
                            <x-ui.button size="sm" variant="secondary" class="mt-2">{{ __('Add') }}</x-ui.button>
                        </form>
                    </div>

                    <form method="post" action="{{ route('admin.products.variants.update', $product) }}" class="mt-6">
                        @csrf @method('put')
                        <div class="mb-4 flex flex-wrap gap-4 text-sm">
                            <span class="font-medium">{{ __('Colours') }}:</span>
                            @foreach ($product->colors as $color)<label class="flex items-center gap-1.5"><input type="hidden" name="colors[{{ $color->id }}]" value="0"><input type="checkbox" name="colors[{{ $color->id }}]" value="1" @checked($color->is_active) class="accent-brand-600"><span class="size-3.5 rounded-full ring-1 ring-line-strong" style="background: {{ $color->hex }}"></span>{{ $color->name }}</label>@endforeach
                        </div>
                        @if ($product->sizes->isNotEmpty())
                            <div class="mb-4 flex flex-wrap gap-4 text-sm">
                                <span class="font-medium">{{ __('Sizes') }}:</span>
                                @foreach ($product->sizes as $size)<label class="flex items-center gap-1.5"><input type="hidden" name="sizes[{{ $size->id }}]" value="0"><input type="checkbox" name="sizes[{{ $size->id }}]" value="1" @checked($size->is_active) class="accent-brand-600">{{ $size->label }}</label>@endforeach
                            </div>
                        @endif
                        <div class="max-h-96 overflow-auto rounded-xl border border-line">
                            <table class="table-base">
                                <thead><tr><th>{{ __('SKU') }}</th><th>{{ __('Colour') }}</th><th>{{ __('Size') }}</th><th>{{ __('Extra cost') }}</th><th>{{ __('Active') }}</th></tr></thead>
                                <tbody>
                                    @foreach ($product->variants->sortBy(fn ($v) => [$v->color->position, $v->size?->position]) as $variant)
                                        <tr>
                                            <td class="font-mono text-xs">{{ $variant->sku }}</td>
                                            <td>{{ $variant->color->name }}</td>
                                            <td>{{ $variant->size?->label ?? '—' }}</td>
                                            <td><input name="variants[{{ $variant->id }}][extra_cost]" value="{{ \App\Support\Money::toMajor($variant->extra_cost_amount, $product->currency) }}" inputmode="decimal" class="w-24 rounded-lg border border-line-strong px-2 py-1 text-sm"></td>
                                            <td><input type="hidden" name="variants[{{ $variant->id }}][is_active]" value="0"><input type="checkbox" name="variants[{{ $variant->id }}][is_active]" value="1" @checked($variant->is_active) class="accent-brand-600"></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <x-ui.button size="sm" class="mt-3">{{ __('Save variants') }}</x-ui.button>
                    </form>
                </x-ui.card>

                <x-ui.card :title="__('Print areas')" :description="__('Physical size and resolution requirements. Designs below the minimum DPI cannot be published.')">
                    @foreach ($product->printAreas as $area)
                        <form method="post" action="{{ route('admin.products.print-areas.update', [$product, $area]) }}" class="mb-4 grid gap-3 rounded-xl border border-line p-4 sm:grid-cols-6">
                            @csrf @method('put')
                            <x-form.input name="label" :label="__('Label (:key)', ['key' => $area->key])" :value="$area->label" class="sm:col-span-2" />
                            <x-form.input name="width_mm" type="number" :label="__('Width mm')" :value="$area->width_mm" />
                            <x-form.input name="height_mm" type="number" :label="__('Height mm')" :value="$area->height_mm" />
                            <x-form.input name="min_dpi" type="number" :label="__('Min DPI')" :value="$area->min_dpi" />
                            <x-form.input name="recommended_dpi" type="number" :label="__('Good DPI')" :value="$area->recommended_dpi" />
                            <x-form.checkbox name="is_required" :label="__('Required')" :checked="$area->is_required" class="sm:col-span-4" />
                            <div class="sm:col-span-2 sm:text-right"><x-ui.button size="sm" variant="secondary">{{ __('Save area') }}</x-ui.button></div>
                            <p class="text-xs text-muted sm:col-span-6">{{ __('Recommended upload width: :px px', ['px' => number_format($area->recommendedPixelWidth())]) }}</p>
                        </form>
                    @endforeach
                </x-ui.card>

                <x-ui.card :title="__('Mockups')" :description="__('Without a photo, the vector template is coloured automatically. Area values are fractions (0–1) of the 1000×1000 image and must keep the print area’s proportions.')">
                    <div class="mb-6 grid gap-3 sm:grid-cols-3">
                        @foreach ($product->mockups as $mockup)
                            <div class="rounded-xl border border-line p-3 text-xs">
                                <div class="relative aspect-square rounded-lg bg-sand">
                                    <x-shop.mockup :product="$product" :color="$mockup->color ?? $product->colors->first()" :view="$mockup->view" class="h-full w-full" />
                                    <div class="pointer-events-none absolute border-2 border-dashed border-brand-600" style="left: {{ $mockup->area_x * 100 }}%; top: {{ $mockup->area_y * 100 }}%; width: {{ $mockup->area_width * 100 }}%; height: {{ $mockup->area_height * 100 }}%"></div>
                                </div>
                                <p class="mt-2">{{ $mockup->printArea->label }} · {{ $mockup->view }} · {{ $mockup->color?->name ?? __('all colours') }}</p>
                                <p class="text-muted">x {{ $mockup->area_x }} y {{ $mockup->area_y }} w {{ $mockup->area_width }} h {{ $mockup->area_height }} · {{ $mockup->image_path ? __('photo') : __('template') }}</p>
                            </div>
                        @endforeach
                    </div>
                    <form method="post" action="{{ route('admin.products.mockups.store', $product) }}" enctype="multipart/form-data" class="grid gap-3 rounded-xl border border-line p-4 sm:grid-cols-4">
                        @csrf
                        <p class="text-sm font-semibold sm:col-span-4">{{ __('Add or replace a mockup') }}</p>
                        <x-form.select name="print_area_id" :label="__('Print area')" :options="$product->printAreas->pluck('label', 'id')" />
                        <x-form.select name="product_color_id" :label="__('Colour')" :options="$product->colors->pluck('name', 'id')" :placeholder="__('All colours (template)')" />
                        <x-form.select name="view" :label="__('View')" :options="['front' => __('Front'), 'back' => __('Back'), 'wrap' => __('Wrap')]" />
                        <x-form.input name="image" type="file" :label="__('Photo (optional, square)')" accept="image/png,image/jpeg,image/webp" />
                        <x-form.input name="area_x" :label="__('Area x')" value="0.33" />
                        <x-form.input name="area_y" :label="__('Area y')" value="0.25" />
                        <x-form.input name="area_width" :label="__('Area width')" value="0.34" />
                        <x-form.input name="area_height" :label="__('Area height')" value="0.45" />
                        <div class="sm:col-span-4"><x-ui.button size="sm" variant="secondary">{{ __('Save mockup') }}</x-ui.button></div>
                    </form>
                </x-ui.card>
            @endif
        </div>

        @if ($editing)
            <aside class="space-y-6">
                <x-ui.card :title="__('Preview')">
                    <div class="aspect-square rounded-xl bg-sand"><x-shop.mockup :product="$product" :color="$product->colors->first()" class="h-full w-full p-2" /></div>
                </x-ui.card>
                <x-ui.card :title="__('Printing partners')">
                    @forelse ($product->partnerServices as $service)
                        <p class="flex justify-between text-sm"><a href="{{ route('admin.partners.show', $service->partner) }}" class="hover:text-brand-700">{{ $service->partner->name }}</a><span class="text-muted"><x-ui.money :amount="$service->unit_cost_amount" :currency="$product->currency" /> · {{ $service->production_days }}d</span></p>
                    @empty
                        <p class="text-sm text-warning-700">{{ __('No partner offers this product yet. Orders for it will wait for manual assignment.') }}</p>
                    @endforelse
                </x-ui.card>
            </aside>
        @endif
    </div>
</x-layouts.dashboard>
