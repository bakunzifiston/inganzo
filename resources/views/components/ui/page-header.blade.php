@props(['title', 'description' => null, 'eyebrow' => null])
<div {{ $attributes->class('mb-8 flex flex-wrap items-end justify-between gap-4') }}>
    <div>
        @if ($eyebrow)<p class="eyebrow mb-2">{{ $eyebrow }}</p>@endif
        <h1 class="text-2xl font-bold sm:text-3xl">{{ $title }}</h1>
        @if ($description)<p class="mt-1.5 max-w-2xl text-sm text-muted">{{ $description }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
