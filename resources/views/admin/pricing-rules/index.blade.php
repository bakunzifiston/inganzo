<x-layouts.dashboard :title="__('Pricing rules')" area="admin">
    <x-ui.page-header :title="__('Pricing & commission rules')" :description="__('Customer price = base cost + artist markup + platform margin, rounded up to the price increment (rounding goes to the platform). The most specific active rule applies: product, then product type, then global.')">
        <x-slot:actions><x-ui.button :href="route('admin.pricing-rules.create')"><x-ui.icon name="plus" class="size-4" /> {{ __('New rule') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>

    <x-admin.table-card :rows="$rules">
        <thead><tr><th>{{ __('Rule') }}</th><th>{{ __('Applies to') }}</th><th>{{ __('Platform margin') }}</th><th>{{ __('Artist markup') }}</th><th>{{ __('Rounding') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @foreach ($rules as $rule)
                <tr>
                    <td><a href="{{ route('admin.pricing-rules.edit', $rule) }}" class="font-medium hover:text-brand-700">{{ $rule->name }}</a><p class="text-xs text-muted">{{ $rule->currency }}</p></td>
                    <td>{{ $rule->scopeLabel() }}</td>
                    <td>{{ $rule->platform_margin_bps / 100 }}%@if ($rule->platform_fixed_amount) + {{ \App\Support\Money::format($rule->platform_fixed_amount, $rule->currency) }}@endif</td>
                    <td>{{ $rule->artist_markup_min_bps / 100 }}–{{ $rule->artist_markup_max_bps / 100 }}% <span class="text-muted">({{ __('default :d%', ['d' => $rule->artist_markup_default_bps / 100]) }})</span>@if ($rule->allowsFixedMarkup())<p class="text-xs text-muted">{{ __('Fixed up to :a', ['a' => \App\Support\Money::format($rule->artist_fixed_max_amount, $rule->currency)]) }}</p>@endif</td>
                    <td>{{ number_format($rule->rounding_increment) }}</td>
                    <td><x-ui.badge :tone="$rule->is_active ? 'success' : 'neutral'">{{ $rule->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>@if ($rule->starts_at || $rule->ends_at)<p class="mt-1 text-xs text-muted">{{ $rule->starts_at?->translatedFormat('j M Y') ?? '…' }} – {{ $rule->ends_at?->translatedFormat('j M Y') ?? '…' }}</p>@endif</td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>

    <x-ui.card :title="__('Current price examples')" :description="__('Lowest variant, default artist markup, using the rule that applies today.')" class="mt-6" :padding="false">
        <table class="table-base">
            <thead><tr><th>{{ __('Product') }}</th><th>{{ __('Rule') }}</th><th class="text-right">{{ __('Base cost') }}</th><th class="text-right">{{ __('Artist') }}</th><th class="text-right">{{ __('Platform') }}</th><th class="text-right">{{ __('Customer price') }}</th></tr></thead>
            <tbody>
                @foreach ($examples as $ex)
                    <tr>
                        <td>{{ $ex['product']->translate('name') }}</td>
                        @if ($ex['quote'])
                            <td class="text-xs text-muted">{{ $ex['rule']->name }}</td>
                            <td class="text-right tabular-nums"><x-ui.money :amount="$ex['quote']->baseCost" :currency="$ex['quote']->currency" /></td>
                            <td class="text-right tabular-nums"><x-ui.money :amount="$ex['quote']->artistEarning" :currency="$ex['quote']->currency" /></td>
                            <td class="text-right tabular-nums"><x-ui.money :amount="$ex['quote']->platformMargin" :currency="$ex['quote']->currency" /></td>
                            <td class="text-right font-semibold tabular-nums"><x-ui.money :amount="$ex['quote']->unitPrice" :currency="$ex['quote']->currency" /></td>
                        @else
                            <td colspan="5" class="text-danger-700">{{ __('No active rule — this product cannot be sold.') }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.card>
</x-layouts.dashboard>
