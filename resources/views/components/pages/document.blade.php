@props(['title', 'eyebrow' => null, 'updated' => '26 October 2026', 'draft' => true])
<x-layouts.shop :title="$title">
    <div class="container-page max-w-3xl py-14">
        @if ($eyebrow)<p class="eyebrow">{{ $eyebrow }}</p>@endif
        <h1 class="mt-2 text-4xl font-bold">{{ $title }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('Last updated :date', ['date' => $updated]) }}</p>
        @if ($draft)
            <p class="mt-6 rounded-xl border border-warning-700/20 bg-warning-50 px-4 py-3 text-xs text-warning-700">{{ __('Template text prepared for launch. It must be reviewed by a qualified Rwandan legal adviser before the platform accepts real customers.') }}</p>
        @endif
        <div class="mt-10 space-y-6 text-[15px] leading-relaxed text-ink-soft [&_h2]:mt-10 [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-ink [&_li]:ml-5 [&_li]:list-disc [&_li]:mt-1 [&_a]:link">
            {{ $slot }}
        </div>
    </div>
</x-layouts.shop>
