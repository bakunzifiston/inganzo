@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
    'type' => 'submit',
])
@php
    $base = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 focus-visible:outline-2 focus-visible:outline-offset-2';
    $sizes = [
        'sm' => 'px-3.5 py-1.5 text-xs',
        'md' => 'px-5 py-2.5 text-sm',
        'lg' => 'px-7 py-3.5 text-base',
    ];
    $variants = [
        'primary' => 'bg-brand-600 text-ink hover:bg-brand-500 active:bg-brand-500 focus-visible:outline-brand-700',
        'dark' => 'bg-ink text-white hover:bg-ink-soft focus-visible:outline-ink',
        'accent' => 'bg-brand-600 text-ink hover:bg-brand-500 active:bg-brand-500 focus-visible:outline-brand-700',
        'secondary' => 'border border-line-strong bg-white text-ink hover:border-brand-700 hover:text-brand-800 focus-visible:outline-brand-700',
        'ghost' => 'text-ink hover:bg-sand',
        'danger' => 'bg-danger-700 text-white hover:bg-danger-700/90 focus-visible:outline-danger-700',
        'success' => 'bg-success-600 text-white hover:bg-success-700 focus-visible:outline-success-600',
    ];
    $classes = $base.' '.$sizes[$size].' '.$variants[$variant];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
