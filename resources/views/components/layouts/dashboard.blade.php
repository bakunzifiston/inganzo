@props(['title', 'area' => 'admin', 'subtitle' => null])
@php
    $user = auth()->user();
    $navigation = \App\Support\Navigation::for($area, $user);
    $workspaces = \App\Support\Navigation::workspaces($user);
    $current = collect($workspaces)->firstWhere('area', $area) ?? $workspaces[0];
    $unread = $user->unreadNotifications()->count();
@endphp
<x-layouts.base :title="$title" body-class="bg-paper">
    <div class="min-h-screen lg:flex" x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false">
        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-40 flex w-[272px] -translate-x-full flex-col bg-ink text-sand transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
            :class="sidebar && 'translate-x-0 shadow-[var(--shadow-lift)]'" aria-label="{{ $current['label'] }}">
            <div class="flex h-16 shrink-0 items-center justify-between px-5">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-white" aria-label="{{ __('Storefront') }}"><x-shop.logo class="h-7" :on-dark="true" /></a>
                <button type="button" class="rounded-lg p-1.5 text-sand/70 hover:bg-white/10 hover:text-white lg:hidden" @click="sidebar = false" aria-label="{{ __('Close menu') }}"><x-ui.icon name="x" /></button>
            </div>

            {{-- Workspace switcher: only areas this account can open --}}
            <div class="px-3 pb-3" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" @click="open = ! open" :aria-expanded="open"
                    class="flex w-full items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-left transition hover:border-white/20 hover:bg-white/10">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg {{ $current['tone'] }}"><x-ui.icon :name="$current['icon']" class="size-[18px]" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-white">{{ $current['label'] }}</span>
                        <span class="block truncate text-xs text-sand/60">{{ $current['description'] }}</span>
                    </span>
                    @if (count($workspaces) > 1)<x-ui.icon name="chevron-down" class="size-4 text-sand/50" />@endif
                </button>
                @if (count($workspaces) > 1)
                    <div x-cloak x-show="open" x-transition.origin.top class="mt-1.5 overflow-hidden rounded-xl border border-line bg-white p-1 text-ink shadow-[var(--shadow-lift)]">
                        @foreach ($workspaces as $workspace)
                            <a href="{{ $workspace['url'] }}" @class(['flex items-center gap-3 rounded-lg px-2.5 py-2 text-sm hover:bg-paper', 'font-semibold' => $workspace['area'] === $area])>
                                <span class="grid size-7 place-items-center rounded-md {{ $workspace['tone'] }}"><x-ui.icon :name="$workspace['icon']" class="size-4" /></span>
                                {{ $workspace['label'] }}
                                @if ($workspace['area'] === $area)<x-ui.icon name="check" class="ml-auto size-4 text-brand-700" />@endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <nav class="flex-1 space-y-5 overflow-y-auto px-3 pb-4" aria-label="{{ __('Sections') }}">
                @foreach ($navigation as $group => $items)
                    <div>
                        @if (! is_int($group))<p class="px-3 pb-1.5 text-[11px] font-semibold tracking-[0.12em] text-sand/40 uppercase">{{ $group }}</p>@endif
                        <ul class="space-y-0.5">
                            @foreach ($items as $item)
                                <li>
                                    <a href="{{ $item['url'] }}" @if ($item['active']) aria-current="page" @endif @class([
                                        'group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition',
                                        'bg-white/10 text-white' => $item['active'],
                                        'text-sand/75 hover:bg-white/5 hover:text-white' => ! $item['active'],
                                    ])>
                                        @if ($item['active'])<span class="absolute top-1.5 bottom-1.5 left-0 w-[3px] rounded-full bg-brand-500" aria-hidden="true"></span>@endif
                                        <x-ui.icon :name="$item['icon']" :class="'size-[18px] shrink-0 '.($item['active'] ? 'text-brand-500' : 'text-sand/45 group-hover:text-white')" />
                                        <span class="flex-1 truncate">{{ $item['label'] }}</span>
                                        @if (! empty($item['badge']))
                                            <span class="min-w-5 rounded-full bg-brand-600 px-1.5 text-center text-[11px] leading-5 font-semibold text-ink">{{ $item['badge'] }}</span>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>

            <div class="shrink-0 border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-xl px-2 py-2">
                    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-600 text-xs font-semibold text-ink">{{ $user->initials() }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold text-white">{{ $user->name }}</span>
                        <span class="block truncate text-xs text-sand/60">{{ \App\Support\Navigation::roleLabel($user) }}</span>
                    </span>
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-lg p-2 text-sand/60 hover:bg-white/10 hover:text-white" aria-label="{{ __('Sign out') }}" title="{{ __('Sign out') }}"><x-ui.icon name="logout" class="size-4" /></button>
                    </form>
                </div>
            </div>
        </aside>
        <div x-cloak x-show="sidebar" x-transition.opacity class="fixed inset-0 z-30 bg-ink/30 backdrop-blur-[1px] lg:hidden" @click="sidebar = false"></div>

        {{-- Main --}}
        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-20 border-b border-line bg-white/90 backdrop-blur">
                <div class="mx-auto flex h-16 max-w-[1400px] items-center gap-3 px-4 sm:px-6 lg:px-8">
                    <button type="button" class="-ml-1 rounded-lg p-2 hover:bg-sand lg:hidden" @click="sidebar = true" aria-label="{{ __('Open menu') }}"><x-ui.icon name="menu" /></button>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold">{{ $title }}</p>
                        <p class="hidden truncate text-xs text-muted sm:block">{{ $subtitle ?? $current['label'] }}</p>
                    </div>
                    <div class="ml-auto flex items-center gap-1.5">
                        <a href="{{ route('home') }}" class="hidden items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-muted hover:bg-sand hover:text-ink sm:flex">
                            <x-ui.icon name="store" class="size-4" /> {{ __('View store') }}
                        </a>
                        <a href="{{ route('account.notifications') }}" class="relative rounded-lg p-2 text-muted hover:bg-sand hover:text-ink" aria-label="{{ trans_choice(':count unread notification|:count unread notifications', $unread, ['count' => $unread]) }}">
                            <x-ui.icon name="bell" class="size-5" />
                            @if ($unread > 0)<span class="absolute top-1 right-1 grid min-w-4 place-items-center rounded-full bg-brand-600 px-1 text-[10px] leading-4 font-bold text-ink">{{ min($unread, 99) }}</span>@endif
                        </a>
                        <span class="ml-1 hidden size-8 place-items-center rounded-full bg-ink text-xs font-semibold text-white sm:grid lg:hidden" title="{{ $user->name }}">{{ $user->initials() }}</span>
                    </div>
                </div>
            </header>
            <main id="main" class="mx-auto max-w-[1400px] px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                <div class="mb-6 empty:hidden"><x-ui.flash /></div>
                {{ $slot }}
            </main>
        </div>
    </div>
</x-layouts.base>
