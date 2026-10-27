<x-layouts.shop :title="__('Contact support')">
    <div class="container-page grid max-w-5xl gap-10 py-14 lg:grid-cols-[1fr_300px]">
        <div>
            <x-ui.page-header :eyebrow="__('Help centre')" :title="__('How can we help?')" :description="__('Send us a message and our Kigali team will get back to you, usually within one business day.')" />
            <form method="post" action="{{ route('support.store') }}" class="card space-y-5 p-6">
                @csrf
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="name" :label="__('Your name')" :value="auth()->user()?->name" required />
                    <x-form.input name="email" type="email" :label="__('E-mail')" :value="auth()->user()?->email" required />
                    <x-form.select name="category" :label="__('Topic')" :options="\App\Enums\SupportCategory::options()" required />
                    <x-form.input name="order_number" :label="__('Order number (optional)')" :value="$orderNumber" />
                </div>
                <x-form.input name="subject" :label="__('Subject')" required />
                <x-form.textarea name="message" :label="__('Message')" rows="6" required />
                <x-ui.button>{{ __('Send message') }}</x-ui.button>
            </form>
        </div>
        <aside class="space-y-4 text-sm lg:pt-24">
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('Other ways to reach us') }}</h2>
                <p class="mt-3 flex items-center gap-2 text-muted"><x-ui.icon name="mail" class="size-4" /> {{ app(\App\Services\Settings::class)->get('marketplace.contact_email') }}</p>
                <p class="mt-2 flex items-center gap-2 text-muted"><x-ui.icon name="phone" class="size-4" /> {{ app(\App\Services\Settings::class)->get('marketplace.contact_phone') }}</p>
            </div>
            <div class="card p-5">
                <h2 class="font-semibold">{{ __('Quick links') }}</h2>
                <ul class="mt-3 space-y-2">
                    <li><a href="{{ route('orders.lookup') }}" class="link">{{ __('Track an order') }}</a></li>
                    <li><a href="{{ route('pages.show', 'refunds') }}" class="link">{{ __('Returns & refunds') }}</a></li>
                    <li><a href="{{ route('pages.show', 'delivery') }}" class="link">{{ __('Delivery information') }}</a></li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.shop>
