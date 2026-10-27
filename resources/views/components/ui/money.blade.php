@props(['amount', 'currency' => 'RWF'])
<span {{ $attributes->class('tabular-nums whitespace-nowrap') }}>{{ \App\Support\Money::format($amount, $currency) }}</span>
