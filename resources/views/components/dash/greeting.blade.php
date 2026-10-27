@props(['name', 'subtitle' => null])
@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? __('Good morning') : ($hour < 18 ? __('Good afternoon') : __('Good evening'));
@endphp
<div {{ $attributes->class('mb-6 flex flex-wrap items-end justify-between gap-4') }}>
    <div>
        <p class="text-sm text-muted">{{ now()->translatedFormat('l, j F Y') }}</p>
        <h1 class="mt-1 text-2xl font-bold sm:text-[1.75rem]">{{ $greeting }}, {{ \Illuminate\Support\Str::of($name)->before(' (')->explode(' ')->first() }}</h1>
        @if ($subtitle)<p class="mt-1 max-w-2xl text-sm text-muted">{{ $subtitle }}</p>@endif
    </div>
    @isset($actions)<div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
