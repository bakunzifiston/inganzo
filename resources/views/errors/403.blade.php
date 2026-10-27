@php
    $user = auth()->user();
    $links = array_filter([
        $user?->can('admin.access') ? [__('Admin console'), route('admin.dashboard')] : null,
        $user?->isArtist() ? [__('Artist studio'), route('studio.dashboard')] : null,
        $user?->isPartnerStaff() ? [__('Production portal'), route('partner.dashboard')] : null,
        $user ? [__('My account'), route('account.dashboard')] : [__('Sign in'), route('login')],
    ]);
@endphp
<x-layouts.base :title="__('Access denied')">
    <div class="grid min-h-screen place-items-center px-6 py-16">
        <div class="max-w-md text-center">
            <a href="{{ route('home') }}" class="inline-flex"><x-shop.logo class="h-8" /></a>
            <p class="mt-10 font-display text-6xl font-bold text-brand-700">403</p>
            <h1 class="mt-3 text-2xl font-bold">{{ __('You don’t have access to this page') }}</h1>
            <p class="mt-2 text-sm text-muted">
                @if ($user)
                    {{ __('You are signed in as :email. This area is for a different type of account (for example the artist studio is only for artists, and the production portal only for printing partners).', ['email' => $user->email]) }}
                @else
                    {{ __('Sign in with an account that has access to this area.') }}
                @endif
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                @foreach ($links as [$label, $url])
                    <x-ui.button :href="$url" :variant="$loop->first ? 'primary' : 'secondary'">{{ $label }}</x-ui.button>
                @endforeach
                <x-ui.button :href="route('home')" variant="ghost">{{ __('Go to the shop') }}</x-ui.button>
            </div>
            @if ($user)
                <form method="post" action="{{ route('logout') }}" class="mt-6">@csrf<button class="text-sm text-muted underline hover:text-ink">{{ __('Sign out and use another account') }}</button></form>
            @endif
        </div>
    </div>
</x-layouts.base>
