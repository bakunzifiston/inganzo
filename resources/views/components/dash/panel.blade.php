@props(['title' => null, 'description' => null, 'href' => null, 'linkLabel' => null, 'padding' => true])
<section {{ $attributes->class('flex flex-col rounded-2xl border border-line bg-white shadow-[var(--shadow-card)]') }}>
    @if ($title || isset($actions) || $href)
        <header class="flex flex-wrap items-start justify-between gap-3 px-5 pt-5 pb-1">
            <div class="min-w-0">
                @if ($title)<h2 class="text-[15px] font-semibold">{{ $title }}</h2>@endif
                @if ($description)<p class="mt-0.5 text-xs text-muted">{{ $description }}</p>@endif
            </div>
            <div class="flex items-center gap-2">
                {{ $actions ?? '' }}
                @if ($href)<a href="{{ $href }}" class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-50">{{ $linkLabel ?? __('View all') }} <x-ui.icon name="arrow-right" class="size-3.5" /></a>@endif
            </div>
        </header>
    @endif
    <div @class(['flex-1', 'p-5' => $padding, 'pt-3' => $padding && $title])>{{ $slot }}</div>
</section>
