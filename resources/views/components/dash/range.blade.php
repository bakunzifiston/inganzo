@props(['current', 'route', 'options' => [7, 30, 90], 'param' => 'days'])
<nav {{ $attributes->class('inline-flex rounded-xl border border-line bg-white p-1 text-xs font-semibold') }} aria-label="{{ __('Date range') }}">
    @foreach ($options as $option)
        <a href="{{ route($route, [$param => $option]) }}" @if ($current === $option) aria-current="true" @endif
            @class(['rounded-lg px-3 py-1.5 transition', 'bg-brand-600 text-ink' => $current === $option, 'text-muted hover:text-ink' => $current !== $option])>{{ __(':days days', ['days' => $option]) }}</a>
    @endforeach
</nav>
