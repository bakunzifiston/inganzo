@props(['onDark' => false])
{{-- Colours come from the theme tokens (fill-/stroke- utilities), so the logo follows the palette. --}}
<svg {{ $attributes->merge(['class' => 'h-7']) }} viewBox="0 0 148 32" role="img" aria-label="{{ config('app.name') }}">
    <rect width="32" height="32" rx="8" @class(['fill-brand-600' => $onDark, 'fill-ink' => ! $onDark])/>
    <path d="M6 22l5-6 5 6 5-6 5 6" @class(['stroke-ink' => $onDark, 'stroke-brand-500' => ! $onDark]) stroke-width="3" fill="none" stroke-linejoin="round" stroke-linecap="round"/>
    <path d="M6 13l5-6 5 6 5-6 5 6" @class(['stroke-ink' => $onDark, 'stroke-white' => ! $onDark]) stroke-width="2" fill="none" stroke-linejoin="round" stroke-linecap="round" opacity=".5"/>
    <text x="42" y="23" font-family="Bricolage Grotesque, Inter, sans-serif" font-size="21" font-weight="700" fill="currentColor" letter-spacing="-0.5">{{ config('app.name') }}</text>
</svg>
