<x-layouts.dashboard :title="__('Support')" area="admin">
    <x-ui.page-header :title="__('Support tickets')" :description="__('Customer and artist requests from the contact form.')" />
    <x-admin.table-card :rows="$tickets" :empty="__('No tickets.')">
        <x-slot:toolbar>
            <form method="get" class="flex flex-wrap items-end gap-3">
                <label class="block text-xs font-medium text-muted">{{ __('Search') }}<input type="search" name="q" value="{{ request('q') }}" class="mt-1 block w-56 rounded-lg border border-line-strong px-3 py-1.5 text-sm text-ink"></label>
                <x-admin.filter name="status" :label="__('Status')" :options="\App\Enums\SupportTicketStatus::options()" :value="request('status')" :placeholder="__('Open & pending')" />
            </form>
        </x-slot:toolbar>
        <thead><tr><th>{{ __('Ticket') }}</th><th>{{ __('From') }}</th><th>{{ __('Topic') }}</th><th>{{ __('Status') }}</th><th>{{ __('Received') }}</th></tr></thead>
        <tbody>
            @foreach ($tickets as $ticket)
                <tr>
                    <td><a href="{{ route('admin.support.show', $ticket) }}" class="font-medium hover:text-brand-700">{{ $ticket->subject }}</a><p class="text-xs text-muted">{{ $ticket->reference }}@if ($ticket->order) · {{ $ticket->order->number }}@endif</p></td>
                    <td>{{ $ticket->name }}<p class="text-xs text-muted">{{ $ticket->email }}</p></td>
                    <td>{{ $ticket->category->label() }}</td>
                    <td><x-ui.badge :status="$ticket->status" /></td>
                    <td class="text-muted">{{ $ticket->created_at->diffForHumans() }}</td>
                </tr>
            @endforeach
        </tbody>
    </x-admin.table-card>
</x-layouts.dashboard>
