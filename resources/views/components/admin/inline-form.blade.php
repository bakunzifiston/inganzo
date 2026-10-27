{{-- Small POST form with a submit button and optional confirmation. --}}
@props(['action', 'method' => 'post', 'confirm' => null, 'label', 'variant' => 'secondary', 'size' => 'sm'])
<form method="post" action="{{ $action }}" {{ $attributes->class('inline') }} @if ($confirm) onsubmit="return confirm(@js($confirm))" @endif>
    @csrf
    @if (strtolower($method) !== 'post') @method($method) @endif
    {{ $slot }}
    <x-ui.button :variant="$variant" :size="$size">{{ $label }}</x-ui.button>
</form>
