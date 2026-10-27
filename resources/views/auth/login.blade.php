<x-layouts.auth :title="__('Sign in')">
    <h1 class="text-3xl font-bold">{{ __('Welcome back') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ __('New here?') }} <a href="{{ route('register') }}" class="link">{{ __('Create an account') }}</a></p>
    <form method="post" action="{{ route('login') }}" class="mt-8 space-y-5">
        @csrf
        <x-form.input name="email" type="email" :label="__('E-mail')" autocomplete="email" required autofocus />
        <x-form.input name="password" type="password" :label="__('Password')" autocomplete="current-password" required />
        <div class="flex items-center justify-between">
            <x-form.checkbox name="remember" :label="__('Keep me signed in')" />
            <a href="{{ route('password.request') }}" class="text-sm text-muted underline hover:text-ink">{{ __('Forgot password?') }}</a>
        </div>
        <x-ui.button class="w-full" size="lg">{{ __('Sign in') }}</x-ui.button>
    </form>
</x-layouts.auth>
