<x-layouts.dashboard :title="$discount->exists ? __('Edit discount') : __('New discount')" area="admin">
    <x-ui.page-header :title="$discount->exists ? $discount->code : __('New discount code')" />
    <x-ui.card class="max-w-3xl">
        <form method="post" action="{{ $discount->exists ? route('admin.discounts.update', $discount) : route('admin.discounts.store') }}" class="grid gap-5 sm:grid-cols-2" x-data="{ type: '{{ old('type', $discount->type?->value ?? 'percent') }}' }">
            @csrf @if ($discount->exists) @method('put') @endif
            <x-form.input name="code" :label="__('Code')" :value="$discount->code" required class="uppercase" />
            <x-form.input name="description" :label="__('Internal description')" :value="$discount->description" />
            <x-form.field name="type" :label="__('Type')"><select name="type" x-model="type" class="input">@foreach (\App\Enums\DiscountType::options() as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></x-form.field>
            <div x-show="type === 'percent'"><x-form.input name="percent" type="number" step="0.01" :label="__('Percent off')" :value="$discount->percent_bps ? $discount->percent_bps / 100 : null" /></div>
            <div x-show="type === 'fixed'"><x-form.input name="amount" :label="__('Amount off')" :value="\App\Support\Money::toMajor($discount->amount, $discount->currency ?? 'RWF')" inputmode="decimal" /></div>
            <x-form.select name="currency" :label="__('Currency')" :options="\App\Models\Currency::query()->pluck('code', 'code')" :value="$discount->currency" />
            <x-form.input name="min_subtotal" :label="__('Minimum subtotal')" :value="\App\Support\Money::toMajor($discount->min_subtotal_amount, $discount->currency ?? 'RWF')" inputmode="decimal" />
            <x-form.input name="max_redemptions" type="number" :label="__('Total uses allowed (empty = unlimited)')" :value="$discount->max_redemptions" />
            <x-form.input name="max_per_customer" type="number" :label="__('Uses per customer')" :value="$discount->max_per_customer" required />
            <x-form.input name="starts_at" type="datetime-local" :label="__('Starts')" :value="$discount->starts_at?->format('Y-m-d\TH:i')" />
            <x-form.input name="ends_at" type="datetime-local" :label="__('Ends')" :value="$discount->ends_at?->format('Y-m-d\TH:i')" />
            <x-form.checkbox name="is_active" :label="__('Active')" :checked="$discount->is_active" class="sm:col-span-2" />
            <div class="sm:col-span-2"><x-ui.button>{{ __('Save') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
