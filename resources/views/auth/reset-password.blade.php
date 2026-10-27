<x-layouts.auth :title="__('Choose a new password')">
    <h1 class="text-3xl font-bold">{{ __('Choose a new password') }}</h1>
    <form method="post" action="{{ route('password.update') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" type="email" :label="__('E-mail')" :value="$email" required />
        <x-form.input name="password" type="password" :label="__('New password')" autocomplete="new-password" required />
        <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" required />
        <x-ui.button class="w-full" size="lg">{{ __('Reset password') }}</x-ui.button>
    </form>
</x-layouts.auth>
