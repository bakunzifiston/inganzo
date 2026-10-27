@props(['title' => null, 'description' => null, 'bodyClass' => ''])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name').' — '.__('Original art on things you love') }}</title>
    <meta name="description" content="{{ $description ?? __('Shop T-shirts, hoodies, mugs and more featuring original designs by independent artists from Rwanda and East Africa. Printed locally on demand.') }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%23171923'/%3E%3Cpath d='M6 22l5-6 5 6 5-6 5 6' stroke='%23F5B027' stroke-width='3' fill='none' stroke-linejoin='round'/%3E%3C/svg%3E">
    {{ $head ?? '' }}
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body {{ $attributes->class(['min-h-full', $bodyClass]) }}>
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow">{{ __('Skip to content') }}</a>
    {{ $slot }}
    @livewireScripts
    @stack('scripts')
</body>
</html>
