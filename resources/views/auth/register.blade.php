<x-layouts.auth :title="__('Create account')">
    <div x-data="{ artist: @js((bool) old('as_artist', $asArtist)) }">
        <h1 class="text-3xl font-bold" x-text="artist ? '{{ __('Open your artist shop') }}' : '{{ __('Create your account') }}'">{{ __('Create your account') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('Already registered?') }} <a href="{{ route('login') }}" class="link">{{ __('Sign in') }}</a></p>

        <div class="mt-6 grid grid-cols-2 gap-1 rounded-full bg-sand p-1 text-sm font-semibold">
            <button type="button" @click="artist = false" :class="! artist ? 'bg-white shadow-sm' : 'text-muted'" class="rounded-full py-2">{{ __('I want to shop') }}</button>
            <button type="button" @click="artist = true" :class="artist ? 'bg-white shadow-sm' : 'text-muted'" class="rounded-full py-2">{{ __('I want to sell') }}</button>
        </div>

        <form method="post" action="{{ route('register') }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="as_artist" :value="artist ? 1 : 0">
            <x-form.input name="name" :label="__('Full name')" autocomplete="name" required />
            <x-form.input name="email" type="email" :label="__('E-mail')" autocomplete="email" required />
            <div x-show="artist" x-cloak>
                <x-form.input name="display_name" :label="__('Shop name')" :hint="__('Shown on your public shop, e.g. “Uwase Studio”.')" />
            </div>
            <x-form.input name="password" type="password" :label="__('Password')" autocomplete="new-password" required />
            <x-form.input name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" required />
            <x-form.checkbox name="accept_terms" :label="__('I agree to the terms of service and privacy policy')" />
            <div x-show="artist" x-cloak>
                <x-form.checkbox name="accept_agreement" :label="__('I accept the artist agreement')" :hint="__('I will only upload artwork I own or have permission to sell.')" />
            </div>
            <x-ui.button class="w-full" size="lg">{{ __('Create account') }}</x-ui.button>
            <p class="text-center text-xs text-muted"><a href="{{ route('pages.show', 'terms') }}" class="underline">{{ __('Terms') }}</a> · <a href="{{ route('pages.show', 'privacy') }}" class="underline">{{ __('Privacy') }}</a> · <a href="{{ route('pages.show', 'artist-agreement') }}" class="underline">{{ __('Artist agreement') }}</a></p>
        </form>
    </div>
</x-layouts.auth>
