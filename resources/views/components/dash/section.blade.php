@props(['title', 'description' => null, 'step' => null])
<section {{ $attributes->class('mt-8 first:mt-0') }}>
    <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
        <div class="flex items-center gap-2.5">
            @if ($step)<span class="grid size-6 place-items-center rounded-full bg-ink text-[11px] font-bold text-white" aria-hidden="true">{{ $step }}</span>@endif
            <div>
                <h2 class="text-sm font-semibold tracking-wide text-ink uppercase">{{ $title }}</h2>
                @if ($description)<p class="text-xs text-muted">{{ $description }}</p>@endif
            </div>
        </div>
        @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
    </div>
    {{ $slot }}
</section>
