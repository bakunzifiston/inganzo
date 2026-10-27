@if ($refund->status->value === 'requested')
    <div class="mt-2 flex flex-wrap gap-2">
        <x-admin.inline-form :action="route('admin.refunds.approve', $refund)" :label="__('Approve')" variant="success" />
        <form method="post" action="{{ route('admin.refunds.reject', $refund) }}" class="flex gap-1">
            @csrf
            <input name="note" required placeholder="{{ __('Reason for declining') }}" class="rounded-lg border border-line-strong px-2 py-1 text-xs">
            <x-ui.button size="sm" variant="secondary">{{ __('Decline') }}</x-ui.button>
        </form>
    </div>
@elseif ($refund->status->value === 'approved')
    <form method="post" action="{{ route('admin.refunds.process', $refund) }}" class="mt-2 flex gap-1">
        @csrf
        <input name="provider_reference" required placeholder="{{ __('Transfer reference') }}" class="rounded-lg border border-line-strong px-2 py-1 text-xs">
        <x-ui.button size="sm" variant="danger">{{ __('Mark refunded') }}</x-ui.button>
    </form>
@endif
