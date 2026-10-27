<x-layouts.dashboard :title="__('Copyright reports')" area="admin">
    <x-ui.page-header :title="__('Copyright & IP reports')" :description="__('Upholding a report takes the design down immediately and notifies the artist.')" />
    <x-admin.table-card :rows="$reports" :empty="__('No open reports.')">
        <x-slot:toolbar><form method="get"><x-admin.filter name="status" :label="__('Status')" :options="\App\Enums\CopyrightReportStatus::options()" :value="request('status')" :placeholder="__('Open & under review')" /></form></x-slot:toolbar>
        <thead><tr><th>{{ __('Design') }}</th><th>{{ __('Rights holder') }}</th><th>{{ __('Received') }}</th><th>{{ __('Status') }}</th></tr></thead>
        <tbody>
            @foreach ($reports as $report)
                <tr>
                    <td><a href="{{ route('admin.copyright.show', $report) }}" class="font-medium hover:text-brand-700">{{ $report->artwork->title }}</a><p class="text-xs text-muted">{{ $report->artwork->artist->display_name }}</p></td>
                    <td>{{ $report->rights_holder }}<p class="text-xs text-muted">{{ $report->reporter_email }}</p></td>
                    <td class="text-muted">{{ $report->created_at->translatedFormat('j M Y') }}</td>
                    <td><x-ui.badge :status="$report->status" /></td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
