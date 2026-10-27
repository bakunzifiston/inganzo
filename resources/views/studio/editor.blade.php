@php
    $views = collect($editorConfig['areas'])->pluck('mockup.view')->unique()->values();
@endphp
<x-layouts.dashboard :title="__('Edit :product', ['product' => $product->translate('name')])" area="studio">
    <div class="mb-6 text-sm text-muted">
        <a href="{{ route('studio.artworks.index') }}" class="hover:text-ink">{{ __('Designs') }}</a> /
        <a href="{{ route('studio.artworks.show', $artwork) }}" class="hover:text-ink">{{ $artwork->title }}</a> /
        {{ $product->translate('name') }}
    </div>

    <form method="post" action="{{ route('studio.artworks.products.update', [$artwork, $product]) }}"
        x-data="productEditor(@js($editorConfig))"
        class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]"
        @keydown.window.arrow-left.prevent="nudge(-0.01, 0)" @keydown.window.arrow-right.prevent="nudge(0.01, 0)"
        @keydown.window.arrow-up.prevent="nudge(0, -0.01)" @keydown.window.arrow-down.prevent="nudge(0, 0.01)">
        @csrf @method('put')
        <input type="hidden" name="placements" :value="serialized()">
        <template x-for="id in selectedColors" :key="id"><input type="hidden" name="colors[]" :value="id"></template>
        <input type="hidden" name="default_color_id" :value="previewColor">

        {{-- Canvas --}}
        <div class="card p-4 sm:p-6">
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <template x-for="a in areas" :key="a.id">
                    <button type="button" @click="activeArea = a.id"
                        :class="activeArea === a.id ? 'bg-brand-600 text-ink' : 'bg-sand hover:bg-line'"
                        class="flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium">
                        <span x-text="a.label"></span>
                        <span x-show="placements[a.id].enabled" class="size-2 rounded-full"
                            :class="{ 'bg-success-600': quality(a) === 'pass', 'bg-warning-500': quality(a) === 'warn', 'bg-danger-700': quality(a) === 'fail' }"></span>
                    </button>
                </template>
                <label class="ml-auto flex items-center gap-2 text-sm">
                    <input type="checkbox" x-model="placements[activeArea].enabled" :disabled="area.required" class="size-4 accent-brand-600">
                    <span x-text="area.required ? '{{ __('Required print area') }}' : '{{ __('Print on this area') }}'"></span>
                </label>
            </div>

            <div class="relative mx-auto aspect-square max-w-[640px] touch-none rounded-2xl bg-sand select-none">
                <svg x-ref="canvas" viewBox="0 0 1000 1000" class="h-full w-full" :style="`color: ${fill}`"
                    @pointermove="drag($event)" @pointerup="endDrag()" @pointerleave="endDrag()">
                    <defs>
                        <linearGradient id="shade-editor" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#fff" stop-opacity="0.10"/><stop offset="0.45" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.14"/>
                        </linearGradient>
                        <linearGradient id="cylinder-editor" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0" stop-color="#000" stop-opacity="0.2"/><stop offset="0.25" stop-color="#fff" stop-opacity="0.18"/><stop offset="0.55" stop-color="#fff" stop-opacity="0"/><stop offset="1" stop-color="#000" stop-opacity="0.24"/>
                        </linearGradient>
                    </defs>
                    @foreach ($views as $view)
                        <g x-show="view === '{{ $view }}'">
                            @include('mockups.'.$product->mockup_template, ['fill' => 'currentColor', 'line' => 'rgb(100 116 139 / 0.25)', 'view' => $view, 'uid' => 'editor'])
                        </g>
                    @endforeach

                    @foreach ($editorConfig['areas'] as $a)
                        @php $id = $a['id']; $ref = "areas.find(x => x.id === {$id})"; @endphp
                        <g x-show="view === '{{ $a['mockup']['view'] }}'">
                            <rect :x="box({{ $ref }}).ax" :y="box({{ $ref }}).ay" :width="box({{ $ref }}).aw" :height="box({{ $ref }}).ah" fill="none"
                                :class="activeArea === {{ $id }} ? 'stroke-brand-600' : 'stroke-ink/25'" stroke-width="3" stroke-dasharray="12 8"/>
                            <g x-show="placements[{{ $id }}].enabled">
                                <image href="{{ $editorConfig['image']['url'] }}" :x="box({{ $ref }}).ix" :y="box({{ $ref }}).iy" :width="box({{ $ref }}).iw" :height="box({{ $ref }}).ih" preserveAspectRatio="none"
                                    :transform="`rotate(${placements[{{ $id }}].rotation} ${box({{ $ref }}).cx} ${box({{ $ref }}).cy})`"
                                    @pointerdown.prevent="activeArea = {{ $id }}; startDrag($event)" style="cursor: move"/>
                                <rect x-show="activeArea === {{ $id }}" :x="box({{ $ref }}).x" :y="box({{ $ref }}).y" :width="box({{ $ref }}).w" :height="box({{ $ref }}).h" fill="none" class="stroke-ink" stroke-width="2" pointer-events="none"/>
                            </g>
                        </g>
                    @endforeach
                </svg>
                <p class="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-white/90 px-3 py-1 text-xs text-muted shadow-sm">{{ __('Drag to move · arrow keys to nudge') }}</p>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-[1fr_auto]" x-show="placement.enabled">
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <label for="scale" class="font-medium">{{ __('Size') }}</label>
                        <span class="text-muted" x-text="Math.round(placement.scale * area.width_mm) + ' mm {{ __('wide') }}'"></span>
                    </div>
                    <input id="scale" type="range" :min="minScale" :max="maxScale(area, placement)" step="0.005" :value="placement.scale" @input="setScale($event.target.value)" class="mt-2 w-full accent-brand-600">
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <x-ui.button type="button" variant="secondary" size="sm" @click="centerHorizontally()">{{ __('Centre') }}</x-ui.button>
                    <x-ui.button type="button" variant="secondary" size="sm" @click="centerVertically()">{{ __('Middle') }}</x-ui.button>
                    <x-ui.button type="button" variant="secondary" size="sm" @click="fit()">{{ __('Fit area') }}</x-ui.button>
                    <x-ui.button type="button" variant="secondary" size="sm" @click="fitToQuality()" x-show="quality(area) !== 'pass' && best(area)">{{ __('Best print size') }}</x-ui.button>
                    <x-ui.button type="button" variant="secondary" size="sm" @click="rotate()" aria-label="{{ __('Rotate 90°') }}"><x-ui.icon name="rotate" class="size-4" /></x-ui.button>
                </div>
            </div>

            <div x-show="placement.enabled" class="mt-5 overflow-hidden rounded-xl border text-sm"
                :class="{ 'border-success-600/30 bg-success-50': quality(area) === 'pass', 'border-warning-700/30 bg-warning-50': quality(area) === 'warn', 'border-danger-700/30 bg-danger-50': quality(area) === 'fail' }"
                role="status" aria-live="polite">
                <span x-ref="labels" class="hidden" data-pass="{{ __('Sharp') }}" data-excellent="{{ __('Excellent') }}" data-warn="{{ __('Acceptable — may look soft') }}" data-fail="{{ __('Too low to print') }}"></span>
                <div class="flex items-start gap-3 px-4 py-3"
                    :class="{ 'text-success-700': quality(area) === 'pass', 'text-warning-700': quality(area) === 'warn', 'text-danger-700': quality(area) === 'fail' }">
                    <span class="mt-0.5 grid size-5 shrink-0 place-items-center rounded-full text-[11px] font-bold text-white"
                        :class="{ 'bg-success-600': quality(area) === 'pass', 'bg-warning-700': quality(area) === 'warn', 'bg-danger-700': quality(area) === 'fail' }"
                        x-text="quality(area) === 'pass' ? '✓' : (quality(area) === 'warn' ? '!' : '×')" aria-hidden="true"></span>
                    <p class="flex-1">
                        <strong x-text="dpi(area) + ' DPI — ' + qualityLabel(area)"></strong>
                        <span class="block text-xs opacity-90" x-show="quality(area) === 'fail'">{{ __('Not print-ready: this product cannot be sold at this size.') }}</span>
                    </p>
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1.5 border-t border-black/5 bg-white/60 px-4 py-3 text-xs text-ink-soft sm:grid-cols-3">
                    <div><dt class="text-muted">{{ __('Source file') }}</dt><dd class="font-medium" x-text="`${image.width} × ${image.height} px`"></dd></div>
                    <div><dt class="text-muted">{{ __('Print size') }}</dt><dd class="font-medium" x-text="`${Math.round(measure(area).widthMm)} × ${Math.round(measure(area).heightMm)} mm`"></dd></div>
                    <div><dt class="text-muted">{{ __('Print area') }}</dt><dd class="font-medium" x-text="`${area.width_mm} × ${area.height_mm} mm`"></dd></div>
                    <div><dt class="text-muted">{{ __('Sharp up to') }}</dt><dd class="font-medium" x-text="`${maxWidthAt(area, area.recommended_dpi)} mm wide (${area.recommended_dpi} DPI)`"></dd></div>
                    <div><dt class="text-muted">{{ __('Acceptable up to') }}</dt><dd class="font-medium" x-text="`${maxWidthAt(area, area.min_dpi)} mm wide (${area.min_dpi} DPI)`"></dd></div>
                    <div><dt class="text-muted">{{ __('Needed for this size') }}</dt><dd class="font-medium" x-text="`${requiredPixels(area, area.recommended_dpi).join(' × ')} px`"></dd></div>
                </dl>
                <div class="border-t border-black/5 px-4 py-3 text-xs" x-show="quality(area) !== 'pass'">
                    <template x-if="best(area)">
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="flex-1" x-text="`{{ __('Best print size for this file:') }} ${Math.round(best(area).scale * area.width_mm)} mm {{ __('wide at') }} ${best(area).target}+ DPI.`"></p>
                            <x-ui.button type="button" variant="secondary" size="sm" @click="fitToQuality()">{{ __('Use best print size') }}</x-ui.button>
                        </div>
                    </template>
                    <template x-if="! best(area)">
                        <p class="font-medium text-danger-700">{{ __('This file is too small to print on this product even at the smallest size. Upload the original artwork at a higher resolution.') }}</p>
                    </template>
                    <p class="mt-2 text-muted">{{ __('To print bigger, upload the original file at a higher resolution. Enlarging a small image — including with AI “upscalers” — does not recover real detail; it guesses it, and the result may not match your artwork.') }}</p>
                </div>
            </div>
            @error('placements')<p class="mt-3 text-sm text-danger-700">{{ $message }}</p>@enderror
        </div>

        {{-- Settings --}}
        <div class="space-y-6"
            x-data="{
                pricing: @js($pricing),
                get base() { return this.pricing.base },
                get artist() { return this.pricing.type === 'fixed' ? Math.min(Number(this.pricing.amount) || 0, this.pricing.fixedMax ?? 0) : Math.floor((this.base * Math.round((Number(this.pricing.percent) || 0) * 100) + 5000) / 10000) },
                get platformRaw() { return Math.floor((this.base * this.pricing.marginBps + 5000) / 10000) + this.pricing.marginFixed },
                get price() { const raw = this.base + this.artist + this.platformRaw; const r = this.pricing.rounding; return raw % r === 0 ? raw : raw + (r - raw % r) },
                fmt(v) { return this.pricing.currency + ' ' + Number(v).toLocaleString('en-US') },
            }">
            <x-ui.card :title="__('Colours')" :description="__('Customers can choose any selected colour. The highlighted one is shown first.')">
                <div class="flex flex-wrap gap-3">
                    <template x-for="c in colors" :key="c.id">
                        <div class="flex flex-col items-center gap-1">
                            <button type="button" @click="toggleColor(c.id); if (selectedColors.includes(c.id)) previewColor = c.id"
                                :class="selectedColors.includes(c.id) ? 'ring-2 ring-ink ring-offset-2' : 'opacity-40 ring-1 ring-line-strong'"
                                class="relative size-11 rounded-full transition" :style="`background: ${c.hex}`" :aria-pressed="selectedColors.includes(c.id)" :aria-label="c.name">
                                <span x-show="previewColor === c.id" class="absolute -top-1 -right-1 grid size-4 place-items-center rounded-full bg-brand-600 text-[9px] text-ink">★</span>
                            </button>
                            <span class="text-[11px] text-muted" x-text="c.name"></span>
                        </div>
                    </template>
                </div>
                <p x-show="selectedColors.length === 0" class="mt-3 text-xs text-danger-700">{{ __('Select at least one colour.') }}</p>
                @error('colors')<p class="mt-2 text-xs text-danger-700">{{ $message }}</p>@enderror
            </x-ui.card>

            <x-ui.card :title="__('Your earnings')" :description="__('Your markup is added to the base cost. You earn it on every unit sold.')">
                <div class="grid grid-cols-2 gap-1 rounded-full bg-sand p-1 text-sm font-semibold">
                    <label class="cursor-pointer rounded-full py-1.5 text-center" :class="pricing.type === 'percent' && 'bg-white shadow-sm'"><input type="radio" name="markup_type" value="percent" x-model="pricing.type" class="sr-only">{{ __('Percentage') }}</label>
                    <label class="rounded-full py-1.5 text-center" :class="[pricing.type === 'fixed' && 'bg-white shadow-sm', pricing.fixedMax === null ? 'cursor-not-allowed opacity-40' : 'cursor-pointer']"><input type="radio" name="markup_type" value="fixed" x-model="pricing.type" :disabled="pricing.fixedMax === null" class="sr-only">{{ __('Fixed amount') }}</label>
                </div>
                <div class="mt-4" x-show="pricing.type === 'percent'">
                    <div class="flex items-center justify-between text-sm"><label for="markup_percent" class="font-medium">{{ __('Markup') }}</label><span class="font-semibold" x-text="pricing.percent + '%'"></span></div>
                    <input id="markup_percent" name="markup_percent" type="range" :min="pricing.minBps / 100" :max="pricing.maxBps / 100" step="1" x-model.number="pricing.percent" class="mt-2 w-full accent-brand-600">
                </div>
                <div class="mt-4" x-show="pricing.type === 'fixed'">
                    <label for="markup_amount" class="label">{{ __('Markup per unit (:currency)', ['currency' => $pricing['currency']]) }}</label>
                    <input id="markup_amount" name="markup_amount" type="number" min="0" :max="pricing.fixedMax" step="100" x-model.number="pricing.amount" class="input">
                </div>
                @error('markup')<p class="mt-2 text-xs text-danger-700">{{ $message }}</p>@enderror
                <dl class="mt-5 space-y-2 rounded-xl bg-paper p-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Base cost (production)') }}</dt><dd x-text="fmt(base)"></dd></div>
                    <div class="flex justify-between font-semibold text-brand-700"><dt>{{ __('You earn') }}</dt><dd x-text="fmt(artist)"></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Platform & service') }}</dt><dd x-text="fmt(price - base - artist)"></dd></div>
                    <div class="flex justify-between border-t border-line pt-2 font-semibold"><dt>{{ __('Customer price') }}</dt><dd x-text="fmt(price)"></dd></div>
                </dl>
                <p class="mt-2 text-xs text-muted">{{ __('Larger sizes have a higher base cost; your percentage applies to it.') }}</p>
            </x-ui.card>

            <x-ui.card>
                <input type="hidden" name="is_enabled" value="0">
                <x-form.checkbox name="is_enabled" :label="__('Offer this product in my shop')" :checked="$isEnabled" :hint="__('Disabled products are saved but not sold.')" />
                <div class="mt-5 flex gap-3">
                    <x-ui.button :href="route('studio.artworks.show', $artwork)" variant="secondary" class="flex-1">{{ __('Cancel') }}</x-ui.button>
                    <x-ui.button variant="accent" class="flex-1">{{ __('Save product') }}</x-ui.button>
                </div>
            </x-ui.card>
        </div>
    </form>
</x-layouts.dashboard>
