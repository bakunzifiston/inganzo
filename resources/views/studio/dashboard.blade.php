@php
    use App\Support\Money;
    $enabled = $readiness['pass'] + $readiness['warn'] + $readiness['fail'] + $readiness['pending'];
    $designTotal = $statusCounts->sum();
    $statusTones = ['approved' => 'leaf', 'pending_review' => 'gold', 'rejected' => 'rose', 'suspended' => 'rose', 'draft' => 'ink', 'archived' => 'ink'];
    $stepsDone = collect($checklist)->where('done', true)->count();
    $shopUrl = route('artists.show', $artist);
@endphp
<x-layouts.dashboard :title="__('Studio')" area="studio" :subtitle="$artist->display_name">
    <x-dash.greeting :name="auth()->user()->name" :subtitle="__('Here is how :shop is doing over the last :days days.', ['shop' => $artist->display_name, 'days' => $days])">
        <x-slot:actions>
            <x-dash.range :current="$days" route="studio.dashboard" />
        </x-slot:actions>
    </x-dash.greeting>

    {{-- Quick actions --}}
    <nav class="mb-8 grid grid-cols-2 gap-3 lg:grid-cols-5" aria-label="{{ __('Quick actions') }}">
        @foreach ([
            [__('Upload a design'), __('PNG, JPEG or WebP'), 'upload', route('studio.artworks.create'), true],
            [__('Add a product'), __('Design or ready-made'), 'plus', route('studio.sell'), false],
            [__('View my shop'), str_replace(['https://', 'http://'], '', $shopUrl), 'store', $shopUrl, false],
            [$payout['eligible'] ? __('Request a payout') : __('Earnings & payouts'), $payout['eligible'] ? __(':amount available', ['amount' => Money::format($payout['available'], $payout['currency'])]) : __('Balance and history'), 'wallet', route('studio.earnings'), false],
            [__('Shop settings'), __('Profile, banner and links'), 'settings', route('studio.shop.edit'), false],
        ] as [$label, $hint, $icon, $url, $primary])
            <a href="{{ $url }}" @class([
                'group flex items-center gap-2.5 rounded-2xl border px-3 py-3 transition last:col-span-2 sm:gap-3 sm:px-4 sm:py-3.5 lg:last:col-span-1',
                'border-brand-600 bg-brand-600 text-ink hover:bg-brand-500' => $primary,
                'border-line bg-white hover:border-line-strong hover:shadow-[var(--shadow-lift)]' => ! $primary,
            ])>
                <span @class(['grid size-8 shrink-0 place-items-center rounded-lg sm:size-10 sm:rounded-xl', 'bg-ink/10' => $primary, 'bg-brand-50 text-brand-700' => ! $primary])><x-ui.icon :name="$icon" class="size-5" /></span>
                <span class="min-w-0">
                    <span class="block text-[13px] leading-tight font-semibold sm:truncate sm:text-sm">{{ $label }}</span>
                    <span @class(['hidden truncate text-xs sm:block', 'text-ink/70' => $primary, 'text-muted' => ! $primary])>{{ $hint }}</span>
                </span>
            </a>
        @endforeach
    </nav>

    {{-- 1. KPI cards --}}
    <x-dash.section step="1" :title="__('Key metrics')" :description="__('Last :days days compared with the :days days before', ['days' => $days])">
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-dash.kpi :label="__('Earnings')" :value="Money::format($summary['earnings'], 'RWF')" icon="wallet" tone="clay" :delta="$deltas['earnings']" :href="route('studio.sales')" />
            <x-dash.kpi :label="__('Units sold')" :value="number_format($summary['units'])" icon="package" tone="sky" :delta="$deltas['units']" :href="route('studio.sales')" />
            <x-dash.kpi :label="__('Orders')" :value="number_format($summary['orders'])" icon="receipt" tone="leaf" :delta="$deltas['orders']"
                :hint="$summary['orders'] ? __(':amount per order', ['amount' => Money::format(intdiv($summary['earnings'], max(1, $summary['orders'])), 'RWF')]) : null" :href="route('studio.sales')" />
            <x-dash.kpi :label="__('Designs live')" :value="number_format($summary['published'])" icon="image" tone="neutral"
                :hint="__(':views design views', ['views' => number_format($summary['views'])])" :href="route('studio.artworks.index', ['status' => 'approved'])" />
        </div>

        @if ($inventory['products'] > 0)
            <div class="mt-4 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                <x-dash.kpi :label="__('Ready-made on sale')" :value="number_format($inventory['live'])" icon="package" tone="neutral" :hint="trans_choice(':count product in total|:count products in total', $inventory['products'], ['count' => $inventory['products']])" :href="route('studio.products.index', ['type' => 'finished'])" />
                <x-dash.kpi :label="__('Units in stock')" :value="number_format($inventory['units'])" icon="store" tone="sky" :hint="__('Worth :amount at shelf price', ['amount' => Money::format($inventory['value'], 'RWF')])" :href="route('studio.products.index', ['type' => 'finished'])" />
                <x-dash.kpi :label="__('Low or out of stock')" :value="number_format($inventory['low'] + $inventory['out'])" icon="alert" :tone="($inventory['low'] + $inventory['out']) ? 'clay' : 'leaf'" :hint="__(':low low · :out out', ['low' => $inventory['low'], 'out' => $inventory['out']])" :href="route('studio.products.index', ['type' => 'finished', 'stock' => $inventory['out'] ? 'out' : 'low'])" />
                <x-dash.kpi :label="__('Orders to ship')" :value="number_format($inventory['to_ship'])" icon="truck" :tone="$inventory['to_ship'] ? 'clay' : 'leaf'" :href="route('studio.orders.index', ['state' => 'to_ship'])" />
            </div>
        @endif

        {{-- Wallet --}}
        <div class="mt-4 grid overflow-hidden rounded-2xl border border-line bg-white sm:grid-cols-[repeat(3,1fr)_auto]">
            @foreach ([
                [__('Available to withdraw'), Money::format($summary['available'], 'RWF'), __('Released after delivery + hold period')],
                [__('Pending'), Money::format($summary['held'], 'RWF'), __('Paid orders not yet settled')],
                [__('Paid out'), Money::format($summary['paid_out'], 'RWF'), __('All time')],
            ] as [$label, $value, $hint])
                <div class="border-b border-line px-5 py-4 sm:border-r sm:border-b-0">
                    <p class="text-xs font-medium text-muted">{{ $label }}</p>
                    <p class="mt-1 font-display text-lg font-semibold tabular-nums">{{ $value }}</p>
                    <p class="text-xs text-subtle">{{ $hint }}</p>
                </div>
            @endforeach
            <div class="flex items-center px-5 py-4">
                @if ($payout['eligible'])
                    <x-ui.button :href="route('studio.earnings')" size="sm" class="w-full sm:w-auto">{{ __('Request payout') }}</x-ui.button>
                @else
                    <p class="text-xs text-muted sm:max-w-44">{{ $payout['reasons'][0] ?? '' }}</p>
                @endif
            </div>
        </div>
    </x-dash.section>

    {{-- 2. Graphs --}}
    <x-dash.section step="2" :title="__('Analytics')" :description="__('Earnings, sales mix and print quality')">
        <div class="grid items-start gap-4 sm:gap-6 xl:grid-cols-3">
            <x-dash.panel :title="__('Daily earnings')" :description="__('Your markup on paid orders, RWF')" class="xl:col-span-2">
                <x-dash.chart :label="__('Daily earnings chart')" height="h-64" :config="[
                    'type' => 'line',
                    'labels' => $trend['labels'],
                    'datasets' => [['label' => __('Earnings'), 'data' => $trend['earnings']]],
                    'currency' => 'RWF',
                ]" />
                <div class="mt-4 grid grid-cols-3 gap-3 border-t border-line pt-4 text-center text-sm">
                    <div><p class="text-xs text-muted">{{ __('Best day') }}</p><p class="font-semibold tabular-nums">{{ Money::format(max($trend['earnings'] ?: [0]), 'RWF') }}</p></div>
                    <div><p class="text-xs text-muted">{{ __('Daily average') }}</p><p class="font-semibold tabular-nums">{{ Money::format(intdiv(array_sum($trend['earnings']), max(1, count($trend['earnings']))), 'RWF') }}</p></div>
                    <div><p class="text-xs text-muted">{{ __('Days with sales') }}</p><p class="font-semibold tabular-nums">{{ count(array_filter($trend['units'])) }} / {{ count($trend['units']) }}</p></div>
                </div>
            </x-dash.panel>

            <x-dash.panel :title="__('Sales by product')" :description="__('Units sold in this period')">
                @if ($salesByProduct->isNotEmpty())
                    <x-dash.chart :label="__('Sales by product chart')" height="h-64" :config="[
                        'type' => 'bar',
                        'indexAxis' => 'y',
                        'labels' => $salesByProduct->pluck('product_name')->all(),
                        'datasets' => [['label' => __('Units'), 'data' => $salesByProduct->map(fn ($row) => (int) $row->units)->all(), 'backgroundColor' => '--color-brand-600']],
                    ]" />
                @else
                    <x-ui.empty icon="chart" :title="__('No sales in this period')" :description="__('Products you sell will be compared here.')" class="py-10" />
                @endif
            </x-dash.panel>

            <x-dash.panel :title="__('Print readiness')" :description="trans_choice(':count product switched on|:count products switched on', $enabled, ['count' => $enabled])">
                @if ($enabled > 0)
                    <x-dash.chart :label="__('Print readiness chart')" height="h-44" :config="[
                        'type' => 'doughnut',
                        'labels' => [__('Print ready'), __('Acceptable'), __('Not printable')],
                        'datasets' => [['data' => [$readiness['pass'], $readiness['warn'], $readiness['fail']], 'backgroundColor' => ['--color-success-600', '--color-warning-600', '--color-danger-600']]],
                    ]" />
                @endif
                <dl class="mt-4 grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-xl bg-success-50 px-2 py-2.5"><dt class="text-[11px] font-medium text-success-700">✓ {{ __('Print ready') }}</dt><dd class="font-display text-xl font-semibold text-success-700">{{ $readiness['pass'] }}</dd></div>
                    <div class="rounded-xl bg-warning-50 px-2 py-2.5"><dt class="text-[11px] font-medium text-warning-700">! {{ __('Acceptable') }}</dt><dd class="font-display text-xl font-semibold text-warning-700">{{ $readiness['warn'] }}</dd></div>
                    <div class="rounded-xl bg-danger-50 px-2 py-2.5"><dt class="text-[11px] font-medium text-danger-700">× {{ __('Not printable') }}</dt><dd class="font-display text-xl font-semibold text-danger-700">{{ $readiness['fail'] }}</dd></div>
                </dl>
            </x-dash.panel>

            <x-dash.panel :title="__('Design pipeline')" :description="trans_choice(':count design|:count designs', $designTotal, ['count' => $designTotal])" :href="route('studio.artworks.index')">
                <div class="space-y-3.5">
                    @foreach (\App\Enums\ArtworkStatus::cases() as $status)
                        @if (($statusCounts[$status->value] ?? 0) > 0 || in_array($status->value, ['draft', 'pending_review', 'approved'], true))
                            <x-dash.meter :label="$status->label()" :value="(int) ($statusCounts[$status->value] ?? 0)" :total="max(1, $designTotal)"
                                :tone="$statusTones[$status->value]" :href="route('studio.artworks.index', ['status' => $status->value])" />
                        @endif
                    @endforeach
                </div>
            </x-dash.panel>

            <x-dash.panel :title="__('Top designs')" :description="__('All-time units sold')">
                @if ($topDesigns->isNotEmpty())
                    <x-dash.chart :label="__('Top designs chart')" height="h-52" :config="[
                        'type' => 'bar',
                        'indexAxis' => 'y',
                        'labels' => $topDesigns->pluck('title')->all(),
                        'datasets' => [['label' => __('Units'), 'data' => $topDesigns->map(fn ($row) => (int) $row->units)->all(), 'backgroundColor' => '--color-success-600']],
                    ]" />
                @else
                    <x-ui.empty icon="sparkles" :title="__('No best sellers yet')" :description="__('Your top designs will be ranked here.')" class="py-10" />
                @endif
            </x-dash.panel>
        </div>
    </x-dash.section>

    {{-- 3. Alerts --}}
    <x-dash.section step="3" :title="__('Alerts')" :description="__('Things that need your action')">
        <div class="space-y-3">
            @if ($stepsDone < count($checklist))
                <div class="rounded-2xl border border-brand-200 bg-brand-50 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold text-brand-800">{{ __('Get your shop ready') }}</p>
                            <p class="text-sm text-ink-soft">{{ __(':done of :total steps complete', ['done' => $stepsDone, 'total' => count($checklist)]) }}</p>
                        </div>
                        <div class="h-2 w-40 overflow-hidden rounded-full bg-white" role="progressbar" aria-valuenow="{{ $stepsDone }}" aria-valuemin="0" aria-valuemax="{{ count($checklist) }}" aria-label="{{ __('Shop set-up progress') }}">
                            <div class="h-full rounded-full bg-brand-600" style="width: {{ round($stepsDone / count($checklist) * 100) }}%"></div>
                        </div>
                    </div>
                    <ol class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($checklist as $step)
                            <li @class(['flex items-center gap-2.5 rounded-xl bg-white px-3 py-2.5 text-sm', 'text-muted' => $step['done']])>
                                <span @class(['grid size-5 shrink-0 place-items-center rounded-full text-[11px] font-bold', 'bg-success-600 text-white' => $step['done'], 'border border-line-strong' => ! $step['done']])>@if ($step['done'])✓<span class="sr-only">{{ __('done') }}</span>@endif</span>
                                <span @class(['flex-1', 'line-through' => $step['done']])>{{ $step['label'] }}</span>
                                @unless ($step['done'])<a href="{{ $step['url'] }}" class="shrink-0 text-xs font-semibold text-brand-700 hover:underline">{{ $step['action'] }}</a>@endunless
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            @unless ($artist->isApproved())
                <x-dash.alert :tone="$artist->status->value === 'pending' ? 'warning' : 'danger'" icon="info" :title="__('Account status: :status.', ['status' => $artist->status->label()])">
                    @if ($artist->status->value === 'pending')
                        {{ __('You can upload and prepare designs now; your shop goes live once our team approves your account and designs.') }}
                    @else
                        {{ $artist->status_reason }}
                    @endif
                </x-dash.alert>
            @endunless

            @if ($inventory['to_ship'] > 0)
                <x-dash.alert tone="warning" icon="truck" :title="trans_choice(':count ready-made item is waiting to be shipped|:count ready-made items are waiting to be shipped', $inventory['to_ship'], ['count' => $inventory['to_ship']])"
                    :href="route('studio.orders.index', ['state' => 'to_ship'])" :action="__('Ship orders')">{{ __('Customers have paid. Pack the items and add tracking details.') }}</x-dash.alert>
            @endif

            @foreach ($stockAlerts as $stockProduct)
                <x-dash.alert :tone="$stockProduct->isInStock() ? 'warning' : 'danger'" icon="package"
                    :title="$stockProduct->isInStock() ? __('“:title” is running low (:count left)', ['title' => $stockProduct->title, 'count' => $stockProduct->totalStock()]) : __('“:title” is out of stock', ['title' => $stockProduct->title])"
                    :href="route('studio.finished.edit', $stockProduct)" :action="__('Update stock')">{{ __('Customers cannot buy options that are out of stock.') }}</x-dash.alert>
            @endforeach

            @foreach ($attention as $item)
                <x-dash.alert :tone="$item->print_check_status->value === 'fail' ? 'danger' : 'warning'" icon="printer"
                    :title="$item->print_check_status->value === 'fail'
                        ? __(':design on :product is not printable (:dpi DPI)', ['design' => $item->artwork->title, 'product' => $item->product->translate('name'), 'dpi' => $item->placements->min('effective_dpi')])
                        : __(':design on :product may print soft (:dpi DPI)', ['design' => $item->artwork->title, 'product' => $item->product->translate('name'), 'dpi' => $item->placements->min('effective_dpi')])"
                    :href="route('studio.artworks.products.edit', [$item->artwork, $item->product])" :action="__('Fix size')">
                    {{ $item->print_check_status->value === 'fail' ? __('It is hidden from your shop until fixed.') : __('Use “Best print size” or upload a larger file.') }}
                </x-dash.alert>
            @endforeach

            @foreach ($needsAttention as $artwork)
                <x-dash.alert :tone="$artwork->latestRejection() ? 'danger' : 'info'" :icon="$artwork->latestRejection() ? 'alert' : 'pencil'"
                    :title="$artwork->latestRejection() ? __('Changes requested on “:title”', ['title' => $artwork->title]) : __('“:title” is still a draft', ['title' => $artwork->title])"
                    :href="route('studio.artworks.show', $artwork)" :action="$artwork->latestRejection() ? __('Review feedback') : __('Finish & submit')">
                    {{ $artwork->latestRejection() ? \Illuminate\Support\Str::limit($artwork->latestRejection()->notes, 140) : __('Submit it for review when it is ready.') }}
                </x-dash.alert>
            @endforeach

            @if ($stepsDone === count($checklist) && $attention->isEmpty() && $needsAttention->isEmpty() && $stockAlerts->isEmpty() && $inventory['to_ship'] === 0)
                <x-dash.alert tone="success" icon="check-circle" :title="__('All caught up')">{{ __('Every product you offer prints sharply and no design needs changes.') }}</x-dash.alert>
            @endif
        </div>
    </x-dash.section>

    {{-- 4. Lists and tables --}}
    <x-dash.section step="4" :title="__('Details')" :description="__('Recent orders, your latest designs and notifications')">
        <x-dash.panel :title="__('Recent orders')" :description="__('Paid items featuring your designs')" :href="route('studio.sales')" :padding="false">
            @if ($recentSales->isEmpty())
                <div class="p-5"><x-ui.empty icon="receipt" :title="__('No sales yet')" :description="__('Share your shop link to get your first order.')" class="py-8">
                    <x-ui.button :href="$shopUrl" variant="secondary" size="sm">{{ __('View my shop') }}</x-ui.button>
                </x-ui.empty></div>
            @else
                <div class="overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>{{ __('Order') }}</th><th>{{ __('Item') }}</th><th>{{ __('Type') }}</th><th class="text-right">{{ __('Qty') }}</th><th class="text-right">{{ __('You earn') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @foreach ($recentSales as $sale)
                                <tr>
                                    <td><p class="font-mono text-xs">{{ $sale->order->number }}</p><p class="text-xs text-muted">{{ $sale->order->paid_at?->diffForHumans() }}</p></td>
                                    <td class="font-medium">{{ $sale->title }}</td>
                                    <td><x-ui.badge :status="$sale->selling_type" /><p class="mt-0.5 text-xs text-muted">{{ $sale->isFinished() ? $sale->variantLabel() : $sale->product_name.' · '.$sale->variantLabel() }}</p></td>
                                    <td class="text-right tabular-nums">{{ $sale->quantity }}</td>
                                    <td class="text-right font-medium tabular-nums">{{ Money::format($sale->artistEarningTotal(), $sale->order->currency) }}</td>
                                    <td><x-ui.badge :status="$sale->fulfillment_status" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-dash.panel>

        <div class="mt-4 grid items-start gap-4 sm:mt-6 sm:gap-6 xl:grid-cols-3">
            <x-dash.panel :title="__('Your latest designs')" :href="route('studio.artworks.index')" class="xl:col-span-2">
                @if ($latestDesigns->isEmpty())
                    <x-ui.empty icon="image" :title="__('No designs yet')" :description="__('Upload a PNG with a transparent background — 3000 px wide prints best.')" class="py-8">
                        <x-ui.button :href="route('studio.artworks.create')" size="sm">{{ __('Upload your first design') }}</x-ui.button>
                    </x-ui.empty>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($latestDesigns as $design)
                            <a href="{{ route('studio.artworks.show', $design) }}" class="group overflow-hidden rounded-xl border border-line transition hover:border-line-strong hover:shadow-[var(--shadow-lift)]">
                                <span class="block aspect-[4/3] bg-checker p-3">
                                    @if ($design->currentVersion?->isReady())
                                        <img src="{{ $design->currentVersion->thumbnailUrl() }}" alt="{{ $design->title }}" loading="lazy" class="h-full w-full object-contain transition group-hover:scale-105">
                                    @elseif (! $design->current_version_id)
                                        <span class="grid h-full place-items-center text-xs text-muted">{{ __('Not exported yet') }}</span>
                                    @else
                                        <span class="grid h-full place-items-center text-xs text-muted">{{ __('Processing…') }}</span>
                                    @endif
                                </span>
                                <span class="block p-3">
                                    <span class="block truncate text-sm font-semibold">{{ $design->title }}</span>
                                    <span class="mt-1.5 flex items-center justify-between gap-2">
                                        <x-ui.badge :status="$design->status" />
                                        <span class="text-xs text-muted">{{ trans_choice(':count for sale|:count for sale', $design->live_products_count, ['count' => $design->live_products_count]) }}</span>
                                    </span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-dash.panel>

            <x-dash.panel :title="__('Notifications')" :href="route('account.notifications')">
                @forelse ($notifications as $notification)
                    <a href="{{ $notification->data['url'] ?? route('account.notifications') }}" class="flex gap-3 border-b border-line py-2.5 text-sm last:border-0 hover:text-brand-700">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand-600" aria-hidden="true"></span>
                        <span class="min-w-0">
                            <span class="block truncate font-medium">{{ $notification->data['title'] ?? '' }}</span>
                            <span class="block text-xs text-subtle">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </a>
                @empty
                    <p class="text-sm text-muted">{{ __('No unread notifications.') }}</p>
                @endforelse
            </x-dash.panel>
        </div>
    </x-dash.section>
</x-layouts.dashboard>
