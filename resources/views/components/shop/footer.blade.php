<footer class="mt-24 bg-ink text-sand">
    <div class="imigongo-band-clay"></div>
    <div class="container-page grid gap-10 py-14 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <x-shop.logo class="h-7 text-white" />
            <p class="mt-4 max-w-sm text-sm leading-relaxed text-sand/70">{{ __('A marketplace for independent artists in Rwanda and East Africa. Every item is printed to order by local partners — no warehouses of unsold stock.') }}</p>
            <form method="post" action="{{ route('locale.update') }}" class="mt-6 flex items-center gap-2 text-sm">
                @csrf
                <x-ui.icon name="globe" class="size-4 text-sand/60" />
                <label for="footer-locale" class="sr-only">{{ __('Language') }}</label>
                <select id="footer-locale" name="locale" onchange="this.form.submit()" class="rounded-lg border border-white/15 bg-transparent py-1.5 pr-8 pl-2 text-sand">
                    @foreach (config('marketplace.locales') as $code => $name)
                        <option value="{{ $code }}" class="text-ink" @selected(app()->getLocale() === $code)>{{ $name }}</option>
                    @endforeach
                </select>
                <noscript><button class="underline">{{ __('Change') }}</button></noscript>
            </form>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-white">{{ __('Shop') }}</h2>
            <ul class="mt-4 space-y-2 text-sm text-sand/70">
                <li><a href="{{ route('shop.index') }}" class="hover:text-white">{{ __('All products') }}</a></li>
                <li><a href="{{ route('artists.index') }}" class="hover:text-white">{{ __('Artists') }}</a></li>
                <li><a href="{{ route('orders.lookup') }}" class="hover:text-white">{{ __('Track an order') }}</a></li>
                <li><a href="{{ route('pages.show', 'delivery') }}" class="hover:text-white">{{ __('Delivery') }}</a></li>
                <li><a href="{{ route('pages.show', 'payments') }}" class="hover:text-white">{{ __('Payment methods') }}</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-white">{{ __('Artists') }}</h2>
            <ul class="mt-4 space-y-2 text-sm text-sand/70">
                <li><a href="{{ route('pages.show', 'sell') }}" class="hover:text-white">{{ __('Sell your art') }}</a></li>
                <li><a href="{{ route('pages.show', 'artist-agreement') }}" class="hover:text-white">{{ __('Artist agreement') }}</a></li>
                <li><a href="{{ route('copyright.create') }}" class="hover:text-white">{{ __('Report infringement') }}</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-sm font-semibold text-white">{{ __('Help') }}</h2>
            <ul class="mt-4 space-y-2 text-sm text-sand/70">
                <li><a href="{{ route('support.create') }}" class="hover:text-white">{{ __('Contact support') }}</a></li>
                <li><a href="{{ route('pages.show', 'refunds') }}" class="hover:text-white">{{ __('Returns & refunds') }}</a></li>
                <li><a href="{{ route('pages.show', 'terms') }}" class="hover:text-white">{{ __('Terms of service') }}</a></li>
                <li><a href="{{ route('pages.show', 'privacy') }}" class="hover:text-white">{{ __('Privacy policy') }}</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-white/10">
        <div class="container-page flex flex-wrap items-center justify-between gap-4 py-6 text-xs text-sand/60">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('Kigali, Rwanda.') }}</p>
            <p class="flex items-center gap-3">
                <span>{{ __('Pay with') }}</span>
                <span class="rounded bg-[#ffcc00] px-1.5 py-0.5 font-bold text-ink">MTN MoMo</span>
                <span class="rounded bg-[#e40000] px-1.5 py-0.5 font-bold text-white">Airtel Money</span>
            </p>
        </div>
    </div>
</footer>
