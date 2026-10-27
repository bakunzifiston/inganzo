<x-layouts.dashboard :title="__('Production jobs')" area="partner">
    <x-ui.page-header :eyebrow="$partner->name" :title="__('Production jobs')" :description="__('Jobs assigned to your facility. Accept new jobs promptly and record quality checks before dispatch.')">
        <x-slot:actions>
            <form method="get" class="flex gap-2" role="search">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <label for="job-search" class="sr-only">{{ __('Search jobs') }}</label>
                <input id="job-search" type="search" name="q" value="{{ $search }}" placeholder="{{ __('Job or order number') }}" class="input w-56">
                <x-ui.button variant="secondary">{{ __('Search') }}</x-ui.button>
            </form>
        </x-slot:actions>
    </x-ui.page-header>

    <x-dash.section step="1" :title="__('Key metrics')" :description="__('Live figures for your facility')">
        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
            <x-dash.kpi :label="__('New jobs to accept')" :value="$stats['new']" icon="bell" tone="gold" :hint="trans_choice(':count due today|:count due today', $stats['due_today'], ['count' => $stats['due_today']])" :href="route('partner.dashboard', ['tab' => 'new'])" />
            <x-dash.kpi :label="__('In production')" :value="$stats['in_production']" icon="printer" tone="sky" :hint="__('Accepted, printing or in QC')" :href="route('partner.dashboard', ['tab' => 'open'])" />
            <x-dash.kpi :label="__('Ready to ship')" :value="$stats['ready']" icon="truck" tone="leaf" :hint="__('Passed quality control')" :href="route('partner.shipments.index')" />
            <x-dash.kpi :label="__('Overdue')" :value="$delayedCount" icon="clock" :tone="$delayedCount > 0 ? 'rose' : 'neutral'" :hint="__('Past the due date')" :href="route('partner.dashboard', ['tab' => 'open'])" />
        </div>
        <dl class="mt-4 grid grid-cols-1 gap-px overflow-hidden rounded-2xl border border-line bg-line text-sm sm:grid-cols-3">
            <div class="bg-white px-5 py-3.5"><dt class="text-xs text-muted">{{ __('Units dispatched, last 7 days') }}</dt><dd class="mt-0.5 font-semibold tabular-nums">{{ number_format($stats['dispatched_week']) }}</dd></div>
            <div class="bg-white px-5 py-3.5"><dt class="text-xs text-muted">{{ __('Average turnaround, last 30 days') }}</dt><dd class="mt-0.5 font-semibold tabular-nums">@if (! $stats['turnaround_jobs'])—@elseif ($stats['turnaround_hours'] < 1){{ __('under 1 hour') }}@else{{ trans_choice(':count hour|:count hours', $stats['turnaround_hours'], ['count' => $stats['turnaround_hours']]) }}@endif <span class="font-normal text-subtle">{{ __('assignment → QC passed') }}</span></dd></div>
            <div class="bg-white px-5 py-3.5"><dt class="text-xs text-muted">{{ __('Quality-check failures, last 30 days') }}</dt><dd class="mt-0.5 font-semibold tabular-nums">{{ $stats['qc_failures'] }}</dd></div>
        </dl>
    </x-dash.section>

    <x-dash.section step="2" :title="__('Analytics')" :description="__('Output and workload')">
        <div class="grid items-start gap-4 sm:gap-6 xl:grid-cols-3">
            <x-dash.panel :title="__('Units dispatched')" :description="__('Per day, last 14 days')" class="xl:col-span-2">
                <x-dash.chart :label="__('Units dispatched chart')" height="h-60" :config="[
                    'type' => 'bar',
                    'labels' => $charts['days'],
                    'datasets' => [['label' => __('Units'), 'data' => $charts['dispatched'], 'backgroundColor' => '--color-success-600']],
                ]" />
            </x-dash.panel>
            <x-dash.panel :title="__('Open jobs by stage')" :description="trans_choice(':count open job|:count open jobs', array_sum($charts['stages']), ['count' => array_sum($charts['stages'])])">
                @if (array_sum($charts['stages']) > 0)
                    <x-dash.chart :label="__('Open jobs by stage chart')" height="h-60" :config="[
                        'type' => 'doughnut',
                        'labels' => array_keys($charts['stages']),
                        'datasets' => [['data' => array_values($charts['stages'])]],
                    ]" />
                @else
                    <x-ui.empty icon="check-circle" :title="__('No open jobs')" class="py-8" />
                @endif
            </x-dash.panel>
        </div>
    </x-dash.section>

    <x-dash.section step="3" :title="__('Alerts')" :description="__('Jobs that need action first')">
        <div class="space-y-3">
            @if ($delayedCount > 0)
                <x-dash.alert tone="danger" icon="clock" :title="trans_choice(':count job is past its due date.|:count jobs are past their due date.', $delayedCount, ['count' => $delayedCount])" :href="route('partner.dashboard', ['tab' => 'open'])" :action="__('View jobs')">
                    {{ __('Finish these first or report an issue so the team can inform customers.') }}
                </x-dash.alert>
            @endif
            @if ($stats['new'] > 0)
                <x-dash.alert tone="warning" icon="bell" :title="trans_choice(':count new job is waiting to be accepted|:count new jobs are waiting to be accepted', $stats['new'], ['count' => $stats['new']])" :href="route('partner.dashboard', ['tab' => 'new'])" :action="__('Accept jobs')" />
            @endif
            @if ($issueCount > 0)
                <x-dash.alert tone="warning" icon="alert" :title="trans_choice(':count active job has a reported issue|:count active jobs have reported issues', $issueCount, ['count' => $issueCount])" :href="route('partner.dashboard', ['tab' => 'issues'])" :action="__('Review')" />
            @endif
            @if ($stats['ready'] > 0)
                <x-dash.alert tone="info" icon="truck" :title="trans_choice(':count job is ready to ship|:count jobs are ready to ship', $stats['ready'], ['count' => $stats['ready']])" :href="route('partner.shipments.index')" :action="__('Create shipment')" />
            @endif
            @if ($delayedCount === 0 && $stats['new'] === 0 && $issueCount === 0 && $stats['ready'] === 0)
                <x-dash.alert tone="success" icon="check-circle" :title="__('Nothing urgent')">{{ __('All jobs are on schedule.') }}</x-dash.alert>
            @endif
        </div>
    </x-dash.section>

    <x-dash.section step="4" :title="__('Jobs')" :description="__('Filter, search and open jobs')">
    @php
        $tabs = [
            'open' => __('Open'), 'new' => __('New'), 'ready' => __('Ready to ship'),
            'issues' => __('Issues'), 'done' => __('Completed'), 'all' => __('All'),
        ];
    @endphp
    <nav class="mb-5 flex flex-wrap gap-2" aria-label="{{ __('Job filters') }}">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('partner.dashboard', array_filter(['tab' => $key, 'q' => $search])) }}"
                @class(['rounded-full px-4 py-1.5 text-sm font-medium transition', 'bg-brand-600 text-ink' => $tab === $key, 'bg-white ring-1 ring-line hover:ring-line-strong' => $tab !== $key])
                @if ($tab === $key) aria-current="page" @endif>
                {{ $label }} <span class="ml-1 opacity-60">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    @if ($jobs->isEmpty())
        <x-ui.empty icon="printer" :title="__('No jobs here')" :description="$search ? __('No jobs match your search.') : __('New jobs appear here as soon as customers pay.')" />
    @else
        <div class="card overflow-x-auto">
            <table class="table-base">
                <thead>
                    <tr>
                        <th>{{ __('Job') }}</th>
                        <th>{{ __('Item') }}</th>
                        <th class="text-right">{{ __('Qty') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Due') }}</th>
                        <th><span class="sr-only">{{ __('Open') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($jobs as $job)
                        <tr @class(['bg-danger-50/40' => $job->isDelayed()])>
                            <td>
                                <a href="{{ route('partner.jobs.show', $job) }}" class="font-semibold hover:text-brand-700">{{ $job->reference }}</a>
                                <p class="text-xs text-muted">{{ __('Order :number', ['number' => $job->orderItem->order->number]) }}</p>
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    @if ($job->orderItem->artworkVersion)
                                        <img src="{{ $job->orderItem->artworkVersion->thumbnailUrl() }}" alt="" class="size-10 rounded-lg bg-sand object-contain p-0.5" loading="lazy">
                                    @endif
                                    <div class="min-w-0">
                                        <p class="truncate font-medium">{{ $job->orderItem->product_name }}</p>
                                        <p class="truncate text-xs text-muted">{{ $job->orderItem->title }} · {{ $job->orderItem->variantLabel() }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="text-right tabular-nums">{{ $job->quantity }}</td>
                            <td>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <x-ui.badge :status="$job->status" />
                                    @if ($job->has_issue)<x-ui.badge tone="danger">{{ __('Issue') }}</x-ui.badge>@endif
                                    @if ($job->reprint_of_id)<x-ui.badge tone="accent">{{ __('Reprint') }}</x-ui.badge>@endif
                                </div>
                            </td>
                            <td @class(['whitespace-nowrap', 'font-semibold text-danger-700' => $job->isDelayed()])>
                                {{ $job->due_at->translatedFormat('j M') }}
                                @if ($job->isDelayed())<span class="block text-xs font-normal">{{ __('Overdue') }}</span>@endif
                            </td>
                            <td class="text-right"><a href="{{ route('partner.jobs.show', $job) }}" class="link text-xs">{{ __('Open') }}</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $jobs->links() }}</div>
    @endif
    </x-dash.section>
</x-layouts.dashboard>
