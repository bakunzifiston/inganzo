<x-layouts.auth :title="__('Reset password')">
    <h1 class="text-3xl font-bold">{{ __('Forgot your password?') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __('Enter your e-mail and we will send you a link to choose a new one.') }}</p>
    <form method="post" action="{{ route('password.email') }}" class="mt-8 space-y-5">
        @csrf
        <x-form.input name="email" type="email" :label="__('E-mail')" required autofocus />
        <x-ui.button class="w-full" size="lg">{{ __('Send reset link') }}</x-ui.button>
        <p class="text-center text-sm"><a href="{{ route('login') }}" class="link">{{ __('Back to sign in') }}</a></p>
    </form>
</x-layouts.auth>
