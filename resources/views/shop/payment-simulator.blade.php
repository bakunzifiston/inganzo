<x-layouts.base :title="__('Payment simulator')">
    <div class="min-h-screen bg-[repeating-linear-gradient(45deg,var(--color-warning-50),var(--color-warning-50)_20px,var(--color-surface)_20px,var(--color-surface)_40px)] px-4 py-16">
        <div class="mx-auto max-w-md">
            <div class="rounded-2xl border-2 border-dashed border-warning-700 bg-white p-8 shadow-[var(--shadow-lift)]">
                <p class="rounded-lg bg-warning-50 px-3 py-2 text-center text-xs font-bold tracking-wider text-warning-700 uppercase">{{ __('Development simulator — no real money moves') }}</p>
                <h1 class="mt-6 text-center text-2xl font-bold">{{ __('Simulated mobile money prompt') }}</h1>
                <p class="mt-2 text-center text-sm text-muted">{{ __('In production the customer approves this on their phone. Here you choose the outcome; a signed callback is sent through the real webhook pipeline.') }}</p>
                <dl class="mt-6 space-y-2 rounded-xl bg-sand p-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Order') }}</dt><dd class="font-medium">{{ $payment->order->number }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Amount') }}</dt><dd class="font-medium"><x-ui.money :amount="$payment->amount" :currency="$payment->currency" /></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Phone') }}</dt><dd class="font-medium">{{ \App\Support\PhoneNumber::display($payment->payer_phone) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Status') }}</dt><dd><x-ui.badge :status="$payment->status" /></dd></div>
                </dl>
                @if ($payment->status->value === 'pending')
                    <form method="post" action="{{ route('payments.simulator.complete', $payment) }}" class="mt-6 grid grid-cols-2 gap-3">
                        @csrf
                        <x-ui.button name="outcome" value="decline" variant="secondary">{{ __('Decline') }}</x-ui.button>
                        <x-ui.button name="outcome" value="approve" variant="success">{{ __('Approve payment') }}</x-ui.button>
                    </form>
                @else
                    <x-ui.button :href="route('orders.track', $payment->order->tracking_token)" class="mt-6 w-full">{{ __('Back to order') }}</x-ui.button>
                @endif
            </div>
        </div>
    </div>
</x-layouts.base>
