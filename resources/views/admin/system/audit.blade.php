<x-layouts.dashboard :title="__('Audit log')" area="admin">
    <x-ui.page-header :title="__('Audit log')" :description="__('Append-only record of sensitive administrative and financial actions. Entries cannot be edited or deleted.')" />
    <x-admin.table-card :rows="$logs" :empty="__('No entries.')">
        <x-slot:toolbar>
            <form method="get" class="flex flex-wrap items-end gap-3">
                <x-admin.filter name="action" :label="__('Action')" :options="$actions->combine($actions)" :value="request('action')" />
                <label class="block text-xs font-medium text-muted">{{ __('User e-mail') }}<input name="user" value="{{ request('user') }}" class="mt-1 block w-48 rounded-lg border border-line-strong px-3 py-1.5 text-sm text-ink"></label>
                <label class="block text-xs font-medium text-muted">{{ __('From') }}<input type="date" name="from" value="{{ request('from') }}" class="mt-1 block rounded-lg border border-line-strong px-2 py-1.5 text-sm text-ink"></label>
                <label class="block text-xs font-medium text-muted">{{ __('To') }}<input type="date" name="to" value="{{ request('to') }}" class="mt-1 block rounded-lg border border-line-strong px-2 py-1.5 text-sm text-ink"></label>
                <x-ui.button size="sm">{{ __('Apply') }}</x-ui.button>
            </form>
        </x-slot:toolbar>
        <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('Action') }}</th><th>{{ __('Subject') }}</th><th>{{ __('Details') }}</th><th>{{ __('IP') }}</th></tr></thead>
        <tbody>
            @foreach ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap text-xs text-muted">{{ $log->created_at->translatedFormat('j M Y H:i:s') }}</td>
                    <td class="text-xs">{{ $log->user?->email ?? __('system') }}</td>
                    <td class="text-xs font-medium">{{ $log->action }}</td>
                    <td class="text-xs text-muted">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                    <td class="max-w-md"><details class="text-xs"><summary class="cursor-pointer truncate text-subtle">{{ \Illuminate\Support\Str::limit(json_encode($log->changes), 80) }}</summary><pre class="mt-1 overflow-x-auto rounded bg-sand p-2 whitespace-pre-wrap">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details></td>
                    <td class="font-mono text-xs text-subtle">{{ $log->ip_address }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
