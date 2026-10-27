<x-layouts.dashboard :title="$ticket->subject" area="admin">
    <x-ui.page-header :eyebrow="$ticket->reference" :title="$ticket->subject" :description="__(':name · :email · :topic', ['name' => $ticket->name, 'email' => $ticket->email, 'topic' => $ticket->category->label()])">
        <x-slot:actions><x-ui.badge :status="$ticket->status" /></x-slot:actions>
    </x-ui.page-header>
    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <x-ui.card :title="__('Message')">
            <p class="whitespace-pre-line text-sm">{{ $ticket->message }}</p>
            <p class="mt-4 text-xs text-muted">{{ $ticket->created_at->translatedFormat('j M Y H:i') }}</p>
            @if ($ticket->order)<p class="mt-2 text-sm">{{ __('Order') }}: <a href="{{ route('admin.orders.show', $ticket->order) }}" class="link">{{ $ticket->order->number }}</a></p>@endif
            @if ($ticket->staff_response)
                <div class="mt-6 rounded-xl bg-sand p-4 text-sm"><p class="text-xs font-semibold text-muted">{{ __('Our response') }} · {{ $ticket->responded_at?->translatedFormat('j M Y H:i') }}</p><p class="mt-1 whitespace-pre-line">{{ $ticket->staff_response }}</p></div>
            @endif
        </x-ui.card>
        <x-ui.card :title="__('Respond')">
            <form method="post" action="{{ route('admin.support.update', $ticket) }}" class="space-y-3">
                @csrf @method('put')
                <x-form.textarea name="staff_response" :label="__('Response')" rows="6" :value="$ticket->staff_response" />
                <x-form.checkbox name="send_email" :label="__('E-mail this response to the customer')" checked />
                <x-form.select name="status" :label="__('Status')" :options="\App\Enums\SupportTicketStatus::options()" :value="$ticket->status" />
                <x-ui.button class="w-full">{{ __('Save') }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.dashboard>
