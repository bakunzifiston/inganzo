@props(['title' => null, 'description' => null, 'wide' => false])
<x-layouts.base :title="$title" :description="$description">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-40 border-b border-line bg-white/95 backdrop-blur" x-data="{ menuOpen: false }" @keydown.escape.window="menuOpen = false">
            {{-- Row 1: menu, logo, search, account --}}
            <div class="container-page flex h-[72px] items-center gap-3 sm:gap-5">
                <button type="button" class="-ml-2 rounded-full p-2 hover:bg-sand" @click="menuOpen = true" :aria-expanded="menuOpen" aria-label="{{ __('Menu') }}">
                    <x-ui.icon name="menu" class="size-6" />
                </button>
                <a href="{{ route('home') }}" class="flex shrink-0 items-center" aria-label="{{ config('app.name') }} — {{ __('Home') }}">
                    <x-shop.logo class="h-8" />
                </a>
                <form action="{{ route('shop.index') }}" method="get" role="search" class="mx-1 hidden flex-1 md:block lg:mx-4">
                    <label class="block rounded-xl border-2 border-brand-200 bg-white transition focus-within:border-brand-600 focus-within:ring-3 focus-within:ring-brand-100">
                        <span class="sr-only">{{ __('Search designs') }}</span>
                        <span class="flex items-center rounded-[10px] bg-white">
                            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Imigongo patterns and Kigali nights') }}"
                                class="w-full border-0 bg-transparent px-4 py-3 text-[15px] placeholder:text-muted focus:ring-0 focus:outline-none">
                            <button class="mr-1.5 rounded-lg p-2 text-ink hover:bg-white" aria-label="{{ __('Search') }}"><x-ui.icon name="search" class="size-5" /></button>
                        </span>
                    </label>
                </form>
                <div class="ml-auto flex items-center gap-1 md:ml-0">
                    @auth
                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button type="button" @click="open = ! open" class="flex items-center rounded-full p-1 hover:bg-sand" :aria-expanded="open" aria-label="{{ __('Account menu') }}">
                                <span class="grid size-9 place-items-center rounded-full bg-brand-600 text-xs font-bold text-ink ring-2 ring-brand-100">{{ auth()->user()->initials() }}</span>
                            </button>
                            <div x-cloak x-show="open" x-transition.origin.top.right class="absolute right-0 mt-2 w-60 overflow-hidden rounded-xl border border-line bg-white py-1 text-sm shadow-[var(--shadow-lift)]">
                                <p class="truncate border-b border-line px-4 py-2.5 text-xs text-muted">{{ auth()->user()->email }}</p>
                                <a href="{{ route('account.dashboard') }}" class="block px-4 py-2 hover:bg-paper">{{ __('My account') }}</a>
                                <a href="{{ route('account.orders.index') }}" class="block px-4 py-2 hover:bg-paper">{{ __('Orders') }}</a>
                                @if (auth()->user()->isArtist())
                                    <a href="{{ route('studio.dashboard') }}" class="block px-4 py-2 font-medium text-brand-700 hover:bg-paper">{{ __('Artist studio') }}</a>
                                @endif
                                @if (auth()->user()->isPartnerStaff())
                                    <a href="{{ route('partner.dashboard') }}" class="block px-4 py-2 font-medium text-success-700 hover:bg-paper">{{ __('Production portal') }}</a>
                                @endif
                                @can('admin.access')
                                    <a href="{{ route('admin.dashboard') }}" class="block px-4 py-2 font-medium hover:bg-paper">{{ __('Admin console') }}</a>
                                @endcan
                                <form method="post" action="{{ route('logout') }}" class="border-t border-line">
                                    @csrf
                                    <button class="block w-full px-4 py-2 text-left hover:bg-paper">{{ __('Sign out') }}</button>
                                </form>
                            </div>
                        </div>
                        <a href="{{ route('account.favorites.index') }}" class="rounded-full p-2 hover:bg-sand" aria-label="{{ __('Favourites') }}"><x-ui.icon name="heart" class="size-6" /></a>
                    @else
                        <a href="{{ route('login') }}" class="rounded-full p-2 hover:bg-sand" aria-label="{{ __('Sign in') }}"><x-ui.icon name="user" class="size-6" /></a>
                        <a href="{{ route('login') }}" class="rounded-full p-2 hover:bg-sand" aria-label="{{ __('Favourites') }}"><x-ui.icon name="heart" class="size-6" /></a>
                    @endauth
                    <a href="{{ route('cart.show') }}" class="relative rounded-full p-2 hover:bg-sand" aria-label="{{ __('Cart') }} ({{ $cartCount }})">
                        <x-ui.icon name="cart" class="size-6" />
                        @if ($cartCount > 0)
                            <span class="absolute top-0 right-0 grid min-w-5 place-items-center rounded-full bg-brand-600 px-1 text-[11px] font-bold text-ink">{{ $cartCount }}</span>
                        @endif
                    </a>
                </div>
            </div>

            {{-- Mobile search --}}
            <form action="{{ route('shop.index') }}" method="get" role="search" class="container-page pb-3 md:hidden">
                <label class="flex items-center rounded-xl border-2 border-brand-200 bg-white focus-within:border-brand-600">
                    <span class="sr-only">{{ __('Search designs') }}</span>
                    <span class="flex w-full items-center rounded-[10px]">
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search designs, artists…') }}" class="w-full border-0 bg-transparent px-3 py-2.5 text-sm focus:ring-0 focus:outline-none">
                        <x-ui.icon name="search" class="mr-3 size-5" />
                    </span>
                </label>
            </form>

            {{-- Row 2: category shortcuts --}}
            <nav class="container-page" aria-label="{{ __('Browse') }}">
                <div class="-mx-4 flex items-stretch gap-1 overflow-x-auto px-4 pb-3 [scrollbar-width:none] sm:mx-0 sm:justify-center sm:px-0">
                    <a href="{{ route('shop.index') }}" class="flex shrink-0 items-center rounded-xl border border-line-strong bg-white px-4 text-center text-xs leading-tight font-semibold shadow-xs hover:border-ink">{!! __('Explore<br>designs') !!}</a>
                    @foreach ($navCategories as $category)
                        <a href="{{ route('shop.category', $category) }}" @class(['group flex w-[76px] shrink-0 flex-col items-center gap-1 rounded-xl px-1 py-1.5 text-center text-xs font-medium hover:bg-sand', 'bg-sand' => request()->route('category')?->id === $category->id])>
                            <x-shop.nav-icon :name="$category->slug" class="size-8 transition group-hover:-translate-y-0.5" />
                            <span class="line-clamp-1">{{ \Illuminate\Support\Str::before($category->translate('name'), ' &') }}</span>
                        </a>
                    @endforeach
                    <span class="mx-2 w-px shrink-0 self-stretch bg-line" aria-hidden="true"></span>
                    <a href="{{ route('shop.index') }}" class="flex shrink-0 items-center rounded-xl border border-line-strong bg-white px-4 text-center text-xs leading-tight font-semibold shadow-xs hover:border-ink">{!! __('Shop all<br>products') !!}</a>
                    @foreach ($navProductTypes as $type)
                        <a href="{{ route('shop.index', ['type' => $type->slug]) }}" @class(['group flex w-[76px] shrink-0 flex-col items-center gap-1 rounded-xl px-1 py-1.5 text-center text-xs font-medium hover:bg-sand', 'bg-sand' => request('type') === $type->slug])>
                            <x-shop.nav-icon :name="$type->slug" class="size-8 transition group-hover:-translate-y-0.5" />
                            <span class="line-clamp-1">{{ $type->translate('name') === 'T-shirts' ? $type->translate('name') : \Illuminate\Support\Str::of($type->translate('name'))->before(' &')->replace(' T-shirts', '') }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>

            {{-- Drawer menu --}}
            <div x-cloak x-show="menuOpen" class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-label="{{ __('Menu') }}">
                <div class="absolute inset-0 bg-ink/40" x-show="menuOpen" x-transition.opacity @click="menuOpen = false"></div>
                <div class="absolute inset-y-0 left-0 flex w-80 max-w-[85vw] flex-col overflow-y-auto bg-white shadow-[var(--shadow-lift)]" x-show="menuOpen"
                    x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full" x-transition:leave="transition duration-150" x-transition:leave-end="-translate-x-full">
                    <div class="flex h-[72px] items-center justify-between border-b border-line px-5">
                        <x-shop.logo class="h-7" />
                        <button type="button" class="rounded-full p-2 hover:bg-sand" @click="menuOpen = false" aria-label="{{ __('Close menu') }}"><x-ui.icon name="x" /></button>
                    </div>
                    <div class="space-y-6 p-5 text-sm">
                        <div class="space-y-1">
                            <a href="{{ route('home') }}" class="block rounded-lg px-3 py-2 font-semibold hover:bg-sand">{{ __('Home') }}</a>
                            <a href="{{ route('shop.index') }}" class="block rounded-lg px-3 py-2 font-semibold hover:bg-sand">{{ __('Shop all designs') }}</a>
                            <a href="{{ route('artists.index') }}" class="block rounded-lg px-3 py-2 font-semibold hover:bg-sand">{{ __('Artists') }}</a>
                        </div>
                        <div>
                            <p class="px-3 pb-1 text-[11px] font-semibold tracking-[0.14em] text-subtle uppercase">{{ __('Products') }}</p>
                            @foreach ($navProductTypes as $type)
                                <a href="{{ route('shop.index', ['type' => $type->slug]) }}" class="flex items-center gap-3 rounded-lg px-3 py-1.5 hover:bg-sand"><x-shop.nav-icon :name="$type->slug" class="size-6" /> {{ $type->translate('name') }}</a>
                            @endforeach
                        </div>
                        <div>
                            <p class="px-3 pb-1 text-[11px] font-semibold tracking-[0.14em] text-subtle uppercase">{{ __('Themes') }}</p>
                            @foreach ($navCategories as $category)
                                <a href="{{ route('shop.category', $category) }}" class="flex items-center gap-3 rounded-lg px-3 py-1.5 hover:bg-sand"><x-shop.nav-icon :name="$category->slug" class="size-6" /> {{ $category->translate('name') }}</a>
                            @endforeach
                        </div>
                        <div class="space-y-1 border-t border-line pt-5">
                            <a href="{{ route('pages.show', 'sell') }}" class="block rounded-lg px-3 py-2 font-semibold text-brand-700 hover:bg-sand">{{ __('Sell your art') }}</a>
                            <a href="{{ route('orders.lookup') }}" class="block rounded-lg px-3 py-2 hover:bg-sand">{{ __('Track an order') }}</a>
                            <a href="{{ route('support.create') }}" class="block rounded-lg px-3 py-2 hover:bg-sand">{{ __('Help & support') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main id="main" class="flex-1">
            @if (session()->hasAny(['success', 'error', 'status', 'info']))
                <div class="container-page pt-6"><x-ui.flash /></div>
            @endif
            {{ $slot }}
        </main>

        <x-shop.footer />
    </div>
</x-layouts.base>
