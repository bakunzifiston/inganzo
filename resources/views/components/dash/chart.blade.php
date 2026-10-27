@props(['config', 'height' => 'h-64', 'label'])
<div {{ $attributes->class($height.' relative') }} x-data="chart(@js($config))">
    <canvas x-ref="canvas" role="img" aria-label="{{ $label }}"></canvas>
</div>
