@props(['title'])
<x-layouts.base :title="$title">
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="flex flex-col px-6 py-8 sm:px-12">
            <a href="{{ route('home') }}" class="inline-flex"><x-shop.logo class="h-7" /></a>
            <div class="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center py-12">
                <x-ui.flash />
                {{ $slot }}
            </div>
        </div>
        <div class="relative hidden overflow-hidden bg-ink lg:block">
            <div class="absolute inset-x-0 top-0 imigongo-band-clay opacity-80"></div>
            <div class="absolute inset-0 flex flex-col justify-end p-14 text-white">
                <p class="eyebrow text-brand-200">{{ __('Made in Rwanda') }}</p>
                <p class="mt-3 max-w-md font-display text-4xl leading-tight font-semibold">{{ __('Original art from independent creators, printed on demand by local partners.') }}</p>
            </div>
            <svg class="absolute -top-10 -right-24 h-[520px] w-[520px] text-brand-700/25" viewBox="0 0 200 200" aria-hidden="true">
                <g fill="none" stroke="currentColor" stroke-width="6">
                    @for ($i = 0; $i < 6; $i++)<path d="M{{ 10 + $i * 12 }} 190 L100 {{ 20 + $i * 12 }} L{{ 190 - $i * 12 }} 190"/>@endfor
                </g>
            </svg>
        </div>
    </div>
</x-layouts.base>
