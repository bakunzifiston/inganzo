@php
    $scope = old('scope', $rule->product_id ? 'product' : ($rule->product_type_id ? 'type' : 'global'));
    $pct = fn ($bps) => $bps === null ? null : rtrim(rtrim(number_format($bps / 100, 2, '.', ''), '0'), '.');
@endphp
<x-layouts.dashboard :title="$rule->exists ? __('Edit pricing rule') : __('New pricing rule')" area="admin">
    <x-ui.page-header :title="$rule->exists ? $rule->name : __('New pricing rule')" :description="__('Changes apply to new carts and checkouts only; placed orders keep their frozen prices. Every change is written to the audit log.')" />
    <form method="post" action="{{ $rule->exists ? route('admin.pricing-rules.update', $rule) : route('admin.pricing-rules.store') }}" class="grid gap-6 xl:grid-cols-[1fr_340px]"
        x-data="{ base: {{ $sampleCost }}, margin: {{ old('platform_margin_percent', $pct($rule->platform_margin_bps) ?? 25) }}, fixed: {{ (int) old('platform_fixed', $rule->platform_fixed_amount ?? 0) }}, artist: {{ old('artist_default_percent', $pct($rule->artist_markup_default_bps) ?? 20) }}, round: {{ old('rounding_increment', $rule->rounding_increment ?? 100) }},
            get artistAmt() { return Math.round(this.base * this.artist / 100) }, get raw() { return this.base + this.artistAmt + Math.round(this.base * this.margin / 100) + Number(this.fixed || 0) },
            get price() { const r = Math.max(1, Number(this.round)); return Math.ceil(this.raw / r) * r }, get platform() { return this.price - this.base - this.artistAmt } }">
        @csrf
        @if ($rule->exists) @method('put') @endif
        <x-ui.card>
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" :label="__('Name')" :value="$rule->name" required class="sm:col-span-2" />
                <div x-data="{ scope: '{{ $scope }}' }" class="grid gap-5 sm:col-span-2 sm:grid-cols-3">
                    <x-form.field name="scope" :label="__('Applies to')">
                        <select name="scope" x-model="scope" class="input"><option value="global">{{ __('All products') }}</option><option value="type">{{ __('A product type') }}</option><option value="product">{{ __('One product') }}</option></select>
                    </x-form.field>
                    <div x-show="scope === 'type'"><x-form.select name="product_type_id" :label="__('Product type')" :options="$types" :value="$rule->product_type_id" :placeholder="__('Choose')" /></div>
                    <div x-show="scope === 'product'"><x-form.select name="product_id" :label="__('Product')" :options="$products" :value="$rule->product_id" :placeholder="__('Choose')" /></div>
                    <x-form.select name="currency" :label="__('Currency')" :options="$currencies" :value="$rule->currency" />
                </div>
                <x-form.input name="platform_margin_percent" type="number" step="0.01" :label="__('Platform margin (% of base cost)')" :value="$pct($rule->platform_margin_bps)" x-model.number="margin" required />
                <x-form.input name="platform_fixed" type="number" step="1" :label="__('Platform fixed fee per unit')" :value="$rule->platform_fixed_amount" x-model.number="fixed" />
                <x-form.input name="artist_min_percent" type="number" step="0.01" :label="__('Artist markup minimum %')" :value="$pct($rule->artist_markup_min_bps)" required />
                <x-form.input name="artist_max_percent" type="number" step="0.01" :label="__('Artist markup maximum %')" :value="$pct($rule->artist_markup_max_bps)" required />
                <x-form.input name="artist_default_percent" type="number" step="0.01" :label="__('Artist markup default %')" :value="$pct($rule->artist_markup_default_bps)" x-model.number="artist" required />
                <x-form.input name="artist_fixed_max" type="number" step="1" :label="__('Max fixed artist markup (empty = disabled)')" :value="$rule->artist_fixed_max_amount" />
                <x-form.input name="rounding_increment" type="number" :label="__('Round prices up to a multiple of')" :value="$rule->rounding_increment" x-model.number="round" required />
                <div></div>
                <x-form.input name="starts_at" type="datetime-local" :label="__('Starts (optional)')" :value="$rule->starts_at?->format('Y-m-d\TH:i')" />
                <x-form.input name="ends_at" type="datetime-local" :label="__('Ends (optional)')" :value="$rule->ends_at?->format('Y-m-d\TH:i')" />
                <x-form.checkbox name="is_active" :label="__('Active')" :checked="$rule->is_active" class="sm:col-span-2" />
            </div>
            <div class="mt-6 flex gap-3"><x-ui.button>{{ __('Save rule') }}</x-ui.button><x-ui.button variant="ghost" :href="route('admin.pricing-rules.index')">{{ __('Cancel') }}</x-ui.button></div>
        </x-ui.card>
        <x-ui.card :title="__('Live example')" :description="__('A product with this base cost, at the default markup.')">
            <label class="label" for="sample-base">{{ __('Base cost') }}</label>
            <input id="sample-base" type="number" x-model.number="base" class="input">
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-muted">{{ __('Base cost') }}</dt><dd x-text="base.toLocaleString()"></dd></div>
                <div class="flex justify-between"><dt class="text-muted">{{ __('Artist earns') }}</dt><dd x-text="artistAmt.toLocaleString()"></dd></div>
                <div class="flex justify-between"><dt class="text-muted">{{ __('Platform keeps (incl. rounding)') }}</dt><dd x-text="platform.toLocaleString()"></dd></div>
                <div class="flex justify-between border-t border-line pt-2 font-semibold"><dt>{{ __('Customer pays') }}</dt><dd x-text="price.toLocaleString()"></dd></div>
            </dl>
        </x-ui.card>
    </form>
</x-layouts.dashboard>
