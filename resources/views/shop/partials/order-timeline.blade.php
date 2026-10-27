@php
    use App\Enums\FulfillmentStatus;
    $f = $order->fulfillment_status;
    $steps = [
        [__('Order placed'), true, $order->placed_at],
        [__('Payment confirmed'), $order->isPaid() || in_array($order->payment_status->value, ['refunded']), $order->paid_at],
        [__('In production'), in_array($f, [FulfillmentStatus::InProduction, FulfillmentStatus::PartiallyShipped, FulfillmentStatus::Shipped, FulfillmentStatus::PartiallyDelivered, FulfillmentStatus::Delivered], true), null],
        [__('Shipped'), in_array($f, [FulfillmentStatus::PartiallyShipped, FulfillmentStatus::Shipped, FulfillmentStatus::PartiallyDelivered, FulfillmentStatus::Delivered], true), $order->shipments->min('dispatched_at')],
        [__('Delivered'), $f === FulfillmentStatus::Delivered, $order->completed_at],
    ];
    if ($order->payment_method === 'cash_on_delivery') {
        $steps[1][0] = __('Pay on delivery');
        $steps[1][1] = true;
    }
@endphp
<ol class="grid grid-cols-5 gap-2" aria-label="{{ __('Order progress') }}">
    @foreach ($steps as $index => [$label, $done, $at])
        <li class="relative">
            <div @class(['h-1.5 rounded-full', 'bg-success-600' => $done, 'bg-line' => ! $done])></div>
            <p @class(['mt-2 text-xs font-semibold sm:text-sm', 'text-ink' => $done, 'text-subtle' => ! $done])>{{ $label }}</p>
            @if ($done && $at)<p class="text-[11px] text-muted">{{ $at->translatedFormat('j M, H:i') }}</p>@endif
        </li>
    @endforeach
</ol>
