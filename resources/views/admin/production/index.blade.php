<x-layouts.dashboard :title="__('Production')" area="admin">
    <x-ui.page-header :title="__('Production board')" :description="__('Open jobs across all printing partners, ordered by due date. Delayed jobs are past their due date and not yet dispatched.')" />

    <div class="mb-6 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('admin.production.index') }}" @class(['rounded-full px-3 py-1.5 font-medium', 'bg-brand-600 text-ink' => ! request()->hasAny(['status', 'delayed', 'issues', 'all']), 'bg-white ring-1 ring-line' => request()->hasAny(['status', 'delayed', 'issues', 'all'])])>{{ __('All open') }}</a>
        <a href="{{ route('admin.production.index', ['delayed' => 1]) }}" @class(['rounded-full px-3 py-1.5 font-medium', 'bg-danger-700 text-white' => request('delayed'), 'bg-white text-danger-700 ring-1 ring-line' => ! request('delayed')])>{{ __('Delayed') }} ({{ $delayedCount }})</a>
        <a href="{{ route('admin.production.index', ['issues' => 1, 'all' => 1]) }}" @class(['rounded-full px-3 py-1.5 font-medium', 'bg-brand-600 text-ink' => request('issues'), 'bg-white ring-1 ring-line' => ! request('issues')])>{{ __('With issues') }}</a>
        @foreach (\App\Enums\ProductionJobStatus::cases() as $status)
            <a href="{{ route('admin.production.index', ['status' => $status->value]) }}" @class(['rounded-full px-3 py-1.5', 'bg-brand-600 text-ink' => request('status') === $status->value, 'bg-white ring-1 ring-line' => request('status') !== $status->value])>{{ $status->label() }} <span class="text-subtle">{{ $counts[$status->value] ?? 0 }}</span></a>
        @endforeach
    </div>

    @if ($unassigned->isNotEmpty())
        <x-ui.card :title="__('Waiting for a printer')" :description="__('Paid items with no eligible partner. Assign them from the order page.')" class="mb-6" :padding="false">
            <ul class="divide-y divide-line text-sm">
                @foreach ($unassigned as $item)
                    <li class="flex items-center justify-between px-5 py-3"><span>{{ $item->title }} · {{ $item->product_name }} ({{ $item->variantLabel() }}) × {{ $item->quantity }}</span><a href="{{ route('admin.orders.show', $item->order) }}" class="link">{{ $item->order->number }}</a></li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <x-admin.table-card :rows="$jobs" :empty="__('No jobs match.')">
        <x-slot:toolbar>
            <form method="get" class="flex items-end gap-3">
                @foreach (request()->only(['status', 'delayed', 'issues', 'all']) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <x-admin.filter name="partner" :label="__('Partner')" :options="$partners->pluck('name', 'id')" :value="request('partner')" />
            </form>
        </x-slot:toolbar>
        <thead><tr><th>{{ __('Job') }}</th><th>{{ __('Item') }}</th><th>{{ __('Partner') }}</th><th>{{ __('Status') }}</th><th>{{ __('Due') }}</th></tr></thead>
        <tbody>
            @foreach ($jobs as $job)
                <tr @class(['bg-danger-50/60' => $job->isDelayed()])>
                    <td><a href="{{ route('admin.orders.show', $job->orderItem->order) }}" class="font-semibold hover:text-brand-700">{{ $job->reference }}</a><p class="text-xs text-muted">{{ $job->orderItem->order->number }}</p></td>
                    <td>{{ $job->orderItem->title }}<p class="text-xs text-muted">{{ $job->orderItem->product_name }} · {{ $job->orderItem->variantLabel() }} × {{ $job->quantity }}</p></td>
                    <td>{{ $job->partner->name }}</td>
                    <td class="space-x-1"><x-ui.badge :status="$job->status" />@if ($job->has_issue)<x-ui.badge tone="warning">{{ __('Issue') }}</x-ui.badge>@endif</td>
                    <td class="whitespace-nowrap {{ $job->isDelayed() ? 'font-semibold text-danger-700' : 'text-muted' }}">{{ $job->due_at->translatedFormat('j M') }}<p class="text-xs font-normal">{{ $job->due_at->diffForHumans() }}</p></td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
