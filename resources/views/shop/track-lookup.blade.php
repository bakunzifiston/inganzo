<x-layouts.shop :title="__('Track an order')">
    <div class="container-page max-w-md py-16">
        <h1 class="text-3xl font-bold">{{ __('Track your order') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('Enter the order number from your confirmation e-mail.') }}</p>
        <form method="post" action="{{ route('orders.find') }}" class="mt-8 space-y-4">
            @csrf
            <x-form.input name="number" :label="__('Order number')" placeholder="IGZ-261026-XXXXX" required class="uppercase" />
            <x-form.input name="email" type="email" :label="__('E-mail used at checkout')" required />
            <x-ui.button class="w-full" size="lg">{{ __('Find my order') }}</x-ui.button>
        </form>
    </div>
</x-layouts.shop>
