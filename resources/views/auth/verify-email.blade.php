<x-layouts.auth :title="__('Verify your e-mail')">
    <div class="grid size-12 place-items-center rounded-full bg-brand-50 text-brand-700"><x-ui.icon name="mail" class="size-6" /></div>
    <h1 class="mt-5 text-3xl font-bold">{{ __('Check your inbox') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __('We sent a verification link to :email. Click it to activate your account.', ['email' => auth()->user()->email]) }}</p>
    <form method="post" action="{{ route('verification.send') }}" class="mt-8">
        @csrf
        <x-ui.button variant="secondary" class="w-full">{{ __('Resend the link') }}</x-ui.button>
    </form>
    <p class="mt-6 text-center text-sm"><a href="{{ route('shop.index') }}" class="link">{{ __('Continue shopping') }}</a></p>
</x-layouts.auth>
