@php
    use App\Support\Money;
    $period = $metrics->from->translatedFormat('j M').' – '.$metrics->to->translatedFormat('j M Y');
    $openQueues = collect($queues)->where('count', '>', 0)->values();
    $hasCharts = $trend || $show['money'] || $topArtists->isNotEmpty();
@endphp
<x-layouts.dashboard :title="__('Overview')" area="admin" :subtitle="$period">
    <x-dash.greeting :name="$user->name" :subtitle="$show['orders']
        ? __(':role · figures for :period. Sales count only paid and partially refunded orders.', ['role' => $roleLabel, 'period' => $period])
        : __(':role · your queues and platform activity for :period.', ['role' => $roleLabel, 'period' => $period])">
        <x-slot:actions>
            <x-dash.range :current="$days" route="admin.dashboard" />
        </x-slot:actions>
    </x-dash.greeting>

    {{-- 1. KPI cards --}}
    <x-dash.section step="1" :title="__('Key metrics')" :description="$period">
        @if ($show['money'] || $show['orders'])
            <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                @if ($show['money'])
                    <x-dash.kpi :label="__('Gross merchandise value')" :value="Money::format($summary['gmv'], 'RWF')" icon="chart" tone="clay"
                        :delta="$deltas['gmv']" :href="$show['orders'] ? route('admin.orders.index', ['payment_status' => 'paid']) : null" />
                    <x-dash.kpi :label="__('Net platform revenue')" :value="Money::format($summary['platform_revenue'], 'RWF')" icon="wallet" tone="leaf"
                        :delta="$deltas['platform_revenue']" :href="route('admin.finance.index')" />
                @endif
                <x-dash.kpi :label="__('Completed sales')" :value="number_format($summary['completed_sales'])" icon="receipt" tone="sky"
                    :delta="$deltas['completed_sales']" :hint="__(':count orders placed', ['count' => number_format($summary['orders_placed'])])" :href="$show['orders'] ? route('admin.orders.index') : null" />
                @if ($show['money'])
                    <x-dash.kpi :label="__('Owed to artists')" :value="Money::format($summary['artist_payable'], 'RWF')" icon="palette" tone="sky"
                        :hint="__(':amount withdrawable now', ['amount' => Money::format($summary['artist_available'], 'RWF')])" :href="route('admin.payouts.index')" />
                @else
                    <x-dash.kpi :label="__('Orders placed')" :value="number_format($summary['orders_placed'])" icon="cart" tone="sky" :delta="$deltas['orders_placed']" :href="route('admin.orders.index')" />
                    <x-dash.kpi :label="__('Awaiting payment')" :value="number_format($summary['pending_payments'])" icon="phone" tone="neutral" :href="route('admin.orders.index', ['status' => 'pending_payment'])" />
                @endif
            </div>
        @endif

        @if ($platform)
            <dl @class(['grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-3 lg:grid-cols-6', 'mt-4' => $show['money'] || $show['orders']])>
                @foreach ([
                    [__('Users'), number_format($platform['users']), __('+:n new', ['n' => $platform['users_new']]), 'users', route('admin.users.index')],
                    [__('Artists'), number_format($platform['artists_approved']), __(':n awaiting approval', ['n' => $platform['artists_pending']]), 'palette', route('admin.artists.index')],
                    [__('Designs live'), number_format($platform['designs_published']), __(':n total · +:new new', ['n' => $platform['designs_total'], 'new' => $platform['designs_new']]), 'image', $show['moderation'] ? route('admin.moderation.index') : null],
                    [__('Products for sale'), number_format($platform['products_listed']), __('on :n blanks', ['n' => $platform['blanks_active']]), 'package', null],
                    [__('Orders'), number_format($platform['orders_total']), __(':n in period', ['n' => $summary['orders_placed']]), 'receipt', $show['orders'] ? route('admin.orders.index') : null],
                    [__('Printing partners'), number_format($platform['partners_active']), __('active'), 'printer', null],
                ] as [$label, $value, $hint, $icon, $url])
                    <div class="bg-white p-4">
                        <dt class="flex items-center gap-2 text-xs font-medium text-muted"><x-ui.icon :name="$icon" class="size-4 text-subtle" /> {{ $label }}</dt>
                        <dd class="mt-1.5 font-display text-xl font-semibold tabular-nums">@if ($url)<a href="{{ $url }}" class="hover:text-brand-700">{{ $value }}</a>@else{{ $value }}@endif</dd>
                        <dd class="mt-0.5 text-xs text-subtle">{{ $hint }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif
    </x-dash.section>

    {{-- 2. Graphs --}}
    @if ($hasCharts)
        <x-dash.section step="2" :title="__('Analytics')" :description="__('Trends and breakdowns for the selected period')">
            <div class="grid items-start gap-4 sm:gap-6 xl:grid-cols-3">
                @if ($trend)
                    <x-dash.panel :title="__('Sales trend')" :description="__('Daily GMV from paid orders')" class="xl:col-span-2">
                        <x-dash.chart :label="__('Daily sales chart')" height="h-72" :config="[
                            'type' => 'line',
                            'labels' => $trend['labels'],
                            'datasets' => [['label' => __('GMV (RWF)'), 'data' => $trend['gmv']]],
                            'currency' => 'RWF',
                        ]" />
                        <div class="mt-4 grid grid-cols-3 gap-3 border-t border-line pt-4 text-center text-sm">
                            <div><p class="text-xs text-muted">{{ __('Orders') }}</p><p class="font-semibold tabular-nums">{{ number_format(array_sum($trend['orders'])) }}</p></div>
                            <div><p class="text-xs text-muted">{{ __('Average order') }}</p><p class="font-semibold tabular-nums">{{ array_sum($trend['orders']) ? Money::format(intdiv(array_sum($trend['gmv']), array_sum($trend['orders'])), 'RWF') : '—' }}</p></div>
                            <div><p class="text-xs text-muted">{{ __('Refunded') }}</p><p class="font-semibold tabular-nums">{{ Money::format($summary['refunded_amount'], 'RWF') }}</p></div>
                        </div>
                    </x-dash.panel>
                @endif

                @if ($show['orders'] && $byType->isNotEmpty())
                    <x-dash.panel :title="__('Sales by product type')" :description="__('Net of item refunds')">
                        <x-dash.chart :label="__('Sales by product type chart')" height="h-52" :config="[
                            'type' => 'doughnut',
                            'labels' => $byType->pluck('label')->all(),
                            'datasets' => [['label' => __('Revenue'), 'data' => $byType->map(fn ($row) => (int) $row->revenue)->all()]],
                        ]" />
                        <ul class="mt-4 space-y-1.5 text-sm">
                            @foreach ($byType as $row)
                                <li class="flex justify-between gap-3"><span class="truncate">{{ $row->label }} <span class="text-subtle">({{ $row->units }})</span></span><span class="tabular-nums">{{ Money::format((int) $row->revenue, 'RWF') }}</span></li>
                            @endforeach
                        </ul>
                    </x-dash.panel>
                @endif

                @if ($show['money'] && $revenueBreakdown->isNotEmpty())
                    <x-dash.panel :title="__('Platform revenue breakdown')" :description="__('Ledger movements in this period, RWF')" :href="route('admin.finance.index')" :link-label="__('Ledger')" class="xl:col-span-2">
                        <x-dash.chart :label="__('Platform revenue breakdown chart')" height="h-64" :config="[
                            'type' => 'bar',
                            'indexAxis' => 'y',
                            'labels' => $revenueBreakdown->map(fn ($row) => $row->type->label())->all(),
                            'datasets' => [['label' => __('Amount'), 'data' => $revenueBreakdown->map(fn ($row) => (int) $row->total)->all(),
                                'backgroundColor' => $revenueBreakdown->map(fn ($row) => $row->total >= 0 ? '--color-success-600' : '--color-danger-600')->all()]],
                            'currency' => 'RWF',
                        ]" />
                    </x-dash.panel>
                @endif

                @if ($topArtists->isNotEmpty())
                    <x-dash.panel :title="__('Top artists')" :description="__('By earnings in this period')" :href="$show['platform'] ? route('admin.artists.index') : null">
                        <x-dash.chart :label="__('Top artists chart')" height="h-64" :config="[
                            'type' => 'bar',
                            'indexAxis' => 'y',
                            'labels' => $topArtists->pluck('display_name')->all(),
                            'datasets' => [['label' => __('Earnings'), 'data' => $topArtists->map(fn ($row) => (int) $row->earnings)->all(), 'backgroundColor' => '--color-brand-600']],
                            'currency' => 'RWF',
                        ]" />
                    </x-dash.panel>
                @endif
            </div>
        </x-dash.section>
    @endif

    {{-- 3. Alerts --}}
    <x-dash.section :step="$hasCharts ? 3 : 2" :title="__('Alerts')" :description="$openQueues->isNotEmpty() ? trans_choice(':count item waiting across your queues|:count items waiting across your queues', $openQueues->sum('count'), ['count' => $openQueues->sum('count')]) : __('All your queues are clear.')">
        <div class="space-y-3">
            @if ($show['money'] && $liabilities['payouts_open'] > 0)
                <x-dash.alert tone="warning" icon="wallet" :title="trans_choice(':count artist payout is waiting|:count artist payouts are waiting', $liabilities['payouts_open'], ['count' => $liabilities['payouts_open']])" :href="route('admin.payouts.index')" :action="__('Review payouts')">
                    {{ __(':amount requested by artists.', ['amount' => Money::format($liabilities['payouts_open_amount'], 'RWF')]) }}
                </x-dash.alert>
            @endif
            @if ($summary['delayed_jobs'] > 0 && auth()->user()->can('orders.manage'))
                <x-dash.alert tone="danger" icon="clock" :title="trans_choice(':count production job is past its due date|:count production jobs are past their due date', $summary['delayed_jobs'], ['count' => $summary['delayed_jobs']])" :href="route('admin.production.index')" :action="__('Production board')">
                    {{ __('Contact the printing partner or reassign the job.') }}
                </x-dash.alert>
            @endif
            @if ($stalePayments)
                <x-dash.alert tone="warning" icon="phone" :title="trans_choice(':count payment has been pending for over 30 minutes|:count payments have been pending for over 30 minutes', $stalePayments, ['count' => $stalePayments])" :href="route('admin.payments.index', ['status' => 'pending'])" :action="__('Payments')">
                    {{ __('Reconciliation checks providers every 5 minutes; unpaid orders expire after 24 hours.') }}
                </x-dash.alert>
            @endif
            @if ($platform && $platform['print_failing'] > 0)
                <x-dash.alert tone="info" icon="printer" :title="trans_choice(':count enabled product is not print-ready|:count enabled products are not print-ready', $platform['print_failing'], ['count' => $platform['print_failing']])">
                    {{ __('They are hidden from the shop until the artist resizes them or uploads a larger file.') }}
                </x-dash.alert>
            @endif

            @if ($queues !== [])
                <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach (collect($queues)->sortByDesc('count') as $queue)
                        <x-dash.queue :label="$queue['label']" :hint="$queue['hint']" :count="$queue['count']" :href="$queue['href']" :icon="$queue['icon']" :tone="$queue['tone']" />
                    @endforeach
                </div>
            @endif
        </div>
    </x-dash.section>

    {{-- 4. Lists and tables --}}
    <x-dash.section :step="$hasCharts ? 4 : 3" :title="__('Details')" :description="__('Latest records and rankings')">
        <div class="grid items-start gap-4 sm:gap-6 lg:grid-cols-2">
            @if ($topProducts->isNotEmpty())
                <x-dash.panel :title="__('Top products')" :description="__('By units sold in this period')" :padding="false">
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>{{ __('Design') }}</th><th class="text-right">{{ __('Units') }}</th><th class="text-right">{{ __('Revenue') }}</th></tr></thead>
                            <tbody>
                                @foreach ($topProducts as $row)
                                    <tr><td><p class="font-medium">{{ $row->title }}</p><p class="text-xs text-muted">{{ $row->product_name }}</p></td><td class="text-right tabular-nums">{{ $row->units }}</td><td class="text-right tabular-nums">{{ Money::format((int) $row->revenue, 'RWF') }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-dash.panel>
            @endif

            @if ($topArtists->isNotEmpty())
                <x-dash.panel :title="__('Artist ranking')" :description="__('By earnings in this period')" :padding="false">
                    <div class="overflow-x-auto">
                        <table class="table-base">
                            <thead><tr><th>{{ __('Artist') }}</th><th class="text-right">{{ __('Units') }}</th><th class="text-right">{{ __('Earnings') }}</th></tr></thead>
                            <tbody>
                                @foreach ($topArtists as $row)
                                    <tr>
                                        <td class="font-medium">@if ($show['platform'])<a href="{{ route('admin.artists.show', $row->slug) }}" class="hover:text-brand-700">{{ $row->display_name }}</a>@else{{ $row->display_name }}@endif</td>
                                        <td class="text-right tabular-nums">{{ $row->units }}</td>
                                        <td class="text-right tabular-nums">{{ Money::format((int) $row->earnings, 'RWF') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-dash.panel>
            @endif

            @if ($show['moderation'])
                <x-dash.panel :title="__('Designs waiting for review')" :description="__('Oldest submissions first')" :href="route('admin.moderation.index')">
                    @forelse ($pendingDesigns as $artwork)
                        <a href="{{ route('admin.moderation.show', $artwork) }}" class="flex items-center gap-3 border-b border-line py-2.5 last:border-0 hover:text-brand-700">
                            <span class="size-11 shrink-0 overflow-hidden rounded-lg bg-checker p-1">
                                @if ($artwork->currentVersion?->isReady())<img src="{{ $artwork->currentVersion->thumbnailUrl() }}" alt="" class="h-full w-full object-contain">@endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium">{{ $artwork->title }}</span>
                                <span class="block truncate text-xs text-muted">{{ $artwork->artist->display_name }} · {{ $artwork->submitted_at?->diffForHumans() }}</span>
                            </span>
                            <x-ui.badge :status="$artwork->artist->status" />
                        </a>
                    @empty
                        <x-ui.empty icon="check-circle" :title="__('Review queue is empty')" :description="__('New submissions will appear here.')" class="py-8" />
                    @endforelse
                </x-dash.panel>
            @endif

            @if ($show['support'])
                <x-dash.panel :title="__('Open support tickets')" :href="route('admin.support.index')">
                    @forelse ($tickets as $ticket)
                        <a href="{{ route('admin.support.show', $ticket) }}" class="block border-b border-line py-2.5 last:border-0 hover:text-brand-700">
                            <span class="flex items-center justify-between gap-3"><span class="truncate text-sm font-medium">{{ $ticket->subject }}</span><x-ui.badge :status="$ticket->status" /></span>
                            <span class="block truncate text-xs text-muted">{{ $ticket->category->label() }} · {{ $ticket->name }} · {{ $ticket->created_at->diffForHumans() }}</span>
                        </a>
                    @empty
                        <x-ui.empty icon="mail" :title="__('No open tickets')" class="py-8" />
                    @endforelse
                </x-dash.panel>
            @endif

            @if ($show['money'])
                <x-dash.panel :title="__('Balances')" :description="__('What the platform currently owes')" :padding="false">
                    <table class="table-base">
                        <tbody>
                            <tr><td>{{ __('Owed to artists (held + available)') }}</td><td class="text-right font-medium tabular-nums">{{ Money::format($summary['artist_payable'], 'RWF') }}</td></tr>
                            <tr><td>{{ __('Withdrawable by artists now') }}</td><td class="text-right font-medium tabular-nums">{{ Money::format($summary['artist_available'], 'RWF') }}</td></tr>
                            <tr><td>{{ __('Owed to printing partners') }}</td><td class="text-right font-medium tabular-nums">{{ Money::format($liabilities['partner_payable'], 'RWF') }}</td></tr>
                            <tr><td>{{ __('Payouts in progress') }}</td><td class="text-right font-medium tabular-nums">{{ $liabilities['payouts_open'] }} · {{ Money::format($liabilities['payouts_open_amount'], 'RWF') }}</td></tr>
                        </tbody>
                    </table>
                </x-dash.panel>
            @endif

            @if ($activity->isNotEmpty())
                <x-dash.panel :title="__('Recent activity')" :href="auth()->user()->can('audit.view') ? route('admin.audit.index') : null" :link-label="__('Audit log')">
                    <ol>
                        @foreach ($activity as $event)
                            <x-dash.activity :icon="$event['icon']" :tone="$event['tone']" :time="$event['time']" :href="$event['url']" :last="$loop->last">{{ $event['text'] }}</x-dash.activity>
                        @endforeach
                    </ol>
                </x-dash.panel>
            @endif
        </div>
    </x-dash.section>
</x-layouts.dashboard>
