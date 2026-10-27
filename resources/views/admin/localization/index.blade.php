<x-layouts.dashboard :title="__('Countries & delivery')" area="admin">
    <x-ui.page-header :title="__('Countries, currencies & delivery')" :description="__('Countries are configured without duplicating the application. Orders always keep the currency and amounts they were placed in; exchange rates are indicative only.')" />
    <div class="space-y-6">
        @foreach ($countries as $country)
            <x-ui.card :title="$country->name.' ('.$country->iso2.')'" :description="__('Currency :c · phone :p', ['c' => $country->currency_code, 'p' => $country->phone_code])">
                <x-slot:actions><x-ui.badge :tone="$country->is_active ? 'success' : 'neutral'">{{ $country->is_active ? __('Selling') : __('Not launched') }}</x-ui.badge></x-slot:actions>
                <form method="post" action="{{ route('admin.countries.update', $country) }}" class="grid gap-4 sm:grid-cols-5 sm:items-end">
                    @csrf @method('put')
                    <x-form.input name="tax_rate_percent" type="number" step="0.01" :label="__('VAT % (prices include)')" :value="$country->tax_rate_bps / 100" />
                    <x-form.input name="phone_pattern" :label="__('Mobile number pattern')" :value="$country->phone_pattern" class="font-mono" />
                    <x-form.select name="default_locale" :label="__('Default language')" :options="config('marketplace.locales')" :value="$country->default_locale" />
                    <div class="space-y-2"><x-form.checkbox name="is_active" :label="__('Active')" :checked="$country->is_active" /><x-form.checkbox name="cod_enabled" :label="__('Cash on delivery')" :checked="$country->cod_enabled" /></div>
                    <x-ui.button size="sm" variant="secondary">{{ __('Save country') }}</x-ui.button>
                </form>
                @error('is_active')<p class="mt-2 text-sm text-danger-700">{{ $message }}</p>@enderror

                <h3 class="mt-6 text-sm font-semibold">{{ __('Delivery zones') }}</h3>
                <div class="mt-2 space-y-2">
                    @foreach ($country->deliveryZones as $zone)
                        <form method="post" action="{{ route('admin.delivery-zones.update', $zone) }}" class="grid items-end gap-2 rounded-xl border border-line p-3 text-sm sm:grid-cols-7">
                            @csrf @method('put')
                            <x-form.input name="name" :label="__('Zone')" :value="$zone->name" class="sm:col-span-2" />
                            <x-form.input name="fee" :label="__('Fee')" :value="\App\Support\Money::toMajor($zone->fee_amount, $country->currency_code)" />
                            <x-form.input name="free_over" :label="__('Free over')" :value="\App\Support\Money::toMajor($zone->free_over_amount, $country->currency_code)" />
                            <x-form.input name="min_days" type="number" :label="__('Min days')" :value="$zone->min_days" />
                            <x-form.input name="max_days" type="number" :label="__('Max days')" :value="$zone->max_days" />
                            <div class="flex items-center justify-between gap-2"><x-form.checkbox name="is_active" :label="__('On')" :checked="$zone->is_active" /><x-ui.button size="sm" variant="secondary">{{ __('Save') }}</x-ui.button></div>
                            <p class="text-xs text-muted sm:col-span-7">{{ trans_choice(':count district|:count districts', $zone->districts_count, ['count' => $zone->districts_count]) }}</p>
                        </form>
                    @endforeach
                    <form method="post" action="{{ route('admin.delivery-zones.store') }}" class="grid items-end gap-2 rounded-xl border border-dashed border-line-strong p-3 text-sm sm:grid-cols-7">
                        @csrf
                        <input type="hidden" name="country_id" value="{{ $country->id }}">
                        <x-form.input name="name" :label="__('New zone')" class="sm:col-span-2" />
                        <x-form.input name="fee" :label="__('Fee')" />
                        <x-form.input name="free_over" :label="__('Free over')" />
                        <x-form.input name="min_days" type="number" :label="__('Min days')" value="1" />
                        <x-form.input name="max_days" type="number" :label="__('Max days')" value="3" />
                        <div class="flex items-center justify-between gap-2"><input type="hidden" name="is_active" value="1"><x-ui.button size="sm">{{ __('Add zone') }}</x-ui.button></div>
                    </form>
                </div>
            </x-ui.card>
        @endforeach

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card :title="__('Currencies')" :padding="false">
                <table class="table-base">
                    <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Name') }}</th><th>{{ __('Decimals') }}</th><th>{{ __('Example') }}</th></tr></thead>
                    <tbody>@foreach ($currencies as $currency)<tr><td class="font-mono">{{ $currency->code }}</td><td>{{ $currency->name }}</td><td>{{ $currency->minor_unit }}</td><td>{{ \App\Support\Money::format(1250000, $currency->code) }}</td></tr>@endforeach</tbody>
                </table>
            </x-ui.card>
            <x-ui.card :title="__('Exchange rates (indicative)')">
                <form method="post" action="{{ route('admin.exchange-rates.store') }}" class="grid gap-2 sm:grid-cols-4 sm:items-end">
                    @csrf
                    <x-form.select name="base_currency" :label="__('1 unit of')" :options="$currencies->pluck('code', 'code')" />
                    <x-form.select name="quote_currency" :label="__('equals … of')" :options="$currencies->pluck('code', 'code')" value="USD" />
                    <x-form.input name="rate" :label="__('Rate')" inputmode="decimal" required />
                    <x-ui.button size="sm" variant="secondary">{{ __('Add') }}</x-ui.button>
                    <x-form.input name="source" :label="__('Source (e.g. BNR reference rate)')" class="sm:col-span-4" />
                </form>
                <ul class="mt-4 space-y-1 text-sm">
                    @foreach ($rates as $rate)<li class="flex justify-between"><span>1 {{ $rate->base_currency }} = {{ rtrim(rtrim((string) $rate->rate, '0'), '.') }} {{ $rate->quote_currency }}</span><span class="text-xs text-muted">{{ $rate->effective_at->translatedFormat('j M Y') }} · {{ $rate->source }}</span></li>@endforeach
                </ul>
            </x-ui.card>
        </div>
    </div>
</x-layouts.dashboard>
