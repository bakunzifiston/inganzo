@php $cur = $order->currency; @endphp
<x-layouts.dashboard :title="__('Order :number', ['number' => $order->number])" area="admin">
    <x-ui.page-header :eyebrow="__('Order')" :title="$order->number" :description="__('Placed :date by :name (:email)', ['date' => $order->placed_at->translatedFormat('j M Y, H:i'), 'name' => $order->customer_name, 'email' => $order->email])">
        <x-slot:actions>
            <x-ui.badge :status="$order->status" /><x-ui.badge :status="$order->payment_status" /><x-ui.badge :status="$order->fulfillment_status" />
            <a href="{{ route('orders.track', $order->tracking_token) }}" target="_blank" class="text-sm text-muted underline">{{ __('Customer view') }}</a>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        <div class="space-y-6">
            {{-- Items & production --}}
            <x-ui.card :title="__('Items & production')" :padding="false">
                <ul class="divide-y divide-line">
                    @foreach ($order->items as $item)
                        @php $job = $item->productionJobs->first(fn ($j) => $j->status->isOpen()); $options = $partnerOptions[$item->id]; @endphp
                        <li class="p-5">
                            <div class="flex flex-wrap gap-4">
                                <div class="aspect-square w-24 shrink-0 rounded-xl bg-sand">
                                    @if ($item->artistProduct)
                                        <x-shop.mockup :product="$item->artistProduct->product" :color="$item->variant->color" :version="$item->artworkVersion" :placements="$item->placement_snapshot" class="h-full w-full p-1" />
                                    @elseif ($item->isFinished())
<x-shop.finished-thumb :product="$item->finishedProduct" class="h-full w-full rounded-xl" />
@endif
                                </div>
                                <div class="min-w-0 flex-1 text-sm">
                                    <div class="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p class="font-semibold">{{ $item->title }} <span class="font-normal text-muted">× {{ $item->quantity }}</span></p>
                                            <p class="text-muted">{{ $item->product_name }} · {{ $item->variantLabel() }} · SKU {{ $item->sku }}</p>
                                            <p class="text-xs text-muted">{{ __('Artist') }}: <a class="underline" href="{{ route('admin.artists.show', $item->artist) }}">{{ $item->artist->display_name }}</a></p>
                                        </div>
                                        <x-ui.badge :status="$item->fulfillment_status" />
                                    </div>
                                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs sm:grid-cols-4">
                                        <div><dt class="text-subtle">{{ __('Unit price') }}</dt><dd><x-ui.money :amount="$item->unit_price_amount" :currency="$cur" /></dd></div>
                                        <div><dt class="text-subtle">{{ __('Base cost') }}</dt><dd><x-ui.money :amount="$item->unit_base_cost_amount" :currency="$cur" /></dd></div>
                                        <div><dt class="text-subtle">{{ __('Artist / unit') }}</dt><dd><x-ui.money :amount="$item->unit_artist_earning_amount" :currency="$cur" /></dd></div>
                                        <div><dt class="text-subtle">{{ __('Platform / unit') }}</dt><dd><x-ui.money :amount="$item->unit_platform_margin_amount" :currency="$cur" /></dd></div>
                                    </dl>
                                    @if ($item->isFinished())
                                        <p class="mt-3 rounded-lg bg-sand px-3 py-2 text-xs text-muted">{{ __('Ready-made product shipped by the artist from their own stock. No print job.') }}</p>
                                    @else
                                    <details class="mt-3 text-xs">
                                        <summary class="cursor-pointer font-medium text-muted">{{ __('Print specification') }}</summary>
                                        <ul class="mt-2 space-y-1">
                                            @foreach ($item->placement_snapshot as $spec)
                                                <li>{{ $spec['print_area_label'] }} ({{ $spec['print_area_mm'][0] }}×{{ $spec['print_area_mm'][1] }} mm): {{ __('width :w mm, offset :x/:y, rotation :r°, :dpi DPI', ['w' => $spec['printed_width_mm'], 'x' => round($spec['offset_x'], 3), 'y' => round($spec['offset_y'], 3), 'r' => $spec['rotation'], 'dpi' => $spec['effective_dpi']]) }}</li>
                                            @endforeach
                                            <li><a href="{{ route('media.original', $item->artwork_version_id) }}" class="link">{{ __('Download production file') }}</a> ({{ $item->artworkVersion->width }}×{{ $item->artworkVersion->height }} px)</li>
                                        </ul>
                                    </details>
                                    @endif
                                </div>
                            </div>

                            @foreach ($item->productionJobs as $pj)
                                <div class="mt-4 rounded-xl border border-line bg-paper p-4 text-sm">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p><span class="font-semibold">{{ $pj->reference }}</span> · {{ $pj->partner->name }}@if ($pj->reprint_of_id) · <span class="text-xs text-brand-700">{{ __('reprint') }}</span>@endif</p>
                                        <div class="flex items-center gap-2">
                                            @if ($pj->isDelayed())<x-ui.badge tone="danger">{{ __('Delayed') }}</x-ui.badge>@endif
                                            @if ($pj->has_issue)<x-ui.badge tone="warning">{{ __('Issue reported') }}</x-ui.badge>@endif
                                            <x-ui.badge :status="$pj->status" />
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-muted">{{ __('Assigned :a · due :d · cost :c/unit', ['a' => $pj->assigned_at->translatedFormat('j M H:i'), 'd' => $pj->due_at->translatedFormat('j M'), 'c' => \App\Support\Money::format($pj->unit_cost_amount, $pj->currency)]) }}@if ($pj->failure_reason) · <span class="text-danger-700">{{ $pj->failure_reason }}</span>@endif</p>
                                    @if ($pj->events->isNotEmpty())
                                        <ol class="mt-2 space-y-0.5 border-l border-line pl-3 text-xs text-muted">
                                            @foreach ($pj->events as $event)
                                                <li>{{ $event->created_at->translatedFormat('j M H:i') }} — {{ $event->to_status ? \App\Enums\ProductionJobStatus::tryFrom($event->to_status)?->label() : ucfirst($event->type) }}@if ($event->notes): {{ $event->notes }}@endif @if ($event->user)<span class="text-subtle">({{ $event->user->name }})</span>@endif</li>
                                            @endforeach
                                        </ol>
                                    @endif
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @if ($pj->status->isActive())
                                            <form method="post" action="{{ route('admin.jobs.fail', $pj) }}" class="flex flex-wrap items-center gap-2" onsubmit="return confirm(@js(__('Mark this job as failed?')))">
                                                @csrf
                                                <input name="reason" required placeholder="{{ __('Failure reason') }}" class="rounded-lg border border-line-strong px-2 py-1 text-xs">
                                                <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="cost_incurred" value="1" class="accent-brand-600"> {{ __('Partner cost incurred') }}</label>
                                                <x-ui.button size="sm" variant="danger">{{ __('Mark failed') }}</x-ui.button>
                                            </form>
                                        @elseif ($pj->status->value === 'failed' && ! $job)
                                            <form method="post" action="{{ route('admin.jobs.reprint', $pj) }}" class="flex items-center gap-2">
                                                @csrf
                                                <select name="printing_partner_id" class="rounded-lg border border-line-strong py-1 pr-8 pl-2 text-xs">
                                                    <option value="">{{ __('Same partner') }}</option>
                                                    @foreach ($options['all'] as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                                                </select>
                                                <x-ui.button size="sm" variant="accent">{{ __('Create reprint') }}</x-ui.button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach

                            @if (in_array($item->fulfillment_status->value, ['awaiting_assignment', 'assigned', 'in_production', 'ready'], true) && ($order->isPaid() || $order->payment_method === 'cash_on_delivery'))
                                <form method="post" action="{{ route('admin.orders.items.assign', [$order, $item]) }}" class="mt-3 flex flex-wrap items-center gap-2 text-sm" @if ($job) onsubmit="return confirm(@js(__('Cancel the current job and reassign?')))" @endif>
                                    @csrf
                                    <label for="partner-{{ $item->id }}" class="text-xs font-medium text-muted">{{ $job ? __('Reassign to') : __('Assign to') }}</label>
                                    <select id="partner-{{ $item->id }}" name="printing_partner_id" required class="rounded-lg border border-line-strong py-1.5 pr-8 pl-3 text-sm">
                                        @forelse ($options['all'] as $p)
                                            <option value="{{ $p->id }}" @selected($job?->printing_partner_id === $p->id)>{{ $p->name }}{{ in_array($p->id, $options['eligible'], true) ? ' ✓ '.__('serves this area') : ' — '.__('outside service area') }}</option>
                                        @empty
                                            <option value="">{{ __('No active partner produces this product') }}</option>
                                        @endforelse
                                    </select>
                                    <x-ui.button size="sm" :variant="$job ? 'secondary' : 'accent'">{{ $job ? __('Reassign') : __('Assign') }}</x-ui.button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            {{-- Shipments --}}
            <x-ui.card :title="__('Shipments')">
                @if ($readyItems->isNotEmpty())
                    <form method="post" action="{{ route('admin.orders.ship', $order) }}" class="mb-6 rounded-xl border border-line bg-paper p-4">
                        @csrf
                        <p class="text-sm font-semibold">{{ __('Dispatch items that passed QC') }}</p>
                        <div class="mt-2 flex flex-wrap gap-3 text-sm">
                            @foreach ($readyItems as $ready)
                                <label class="flex items-center gap-2"><input type="checkbox" name="items[]" value="{{ $ready->id }}" checked class="accent-brand-600"> {{ $ready->title }} ({{ $ready->variantLabel() }})</label>
                            @endforeach
                        </div>
                        <div class="mt-3 grid gap-3 sm:grid-cols-3">
                            <input name="carrier" required placeholder="{{ __('Carrier') }}" class="input">
                            <input name="tracking_number" placeholder="{{ __('Tracking number') }}" class="input">
                            <input name="tracking_url" type="url" placeholder="{{ __('Tracking URL') }}" class="input">
                        </div>
                        <x-ui.button size="sm" class="mt-3">{{ __('Create shipment') }}</x-ui.button>
                    </form>
                @endif
                @forelse ($order->shipments as $shipment)
                    <div class="border-b border-line py-4 first:pt-0 last:border-0 last:pb-0 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold">{{ $shipment->reference }} · {{ $shipment->carrier }} @if ($shipment->tracking_number)<span class="font-normal text-muted">#{{ $shipment->tracking_number }}</span>@endif</p>
                            <x-ui.badge :status="$shipment->status" />
                        </div>
                        <p class="text-xs text-muted">{{ $shipment->items->map(fn ($s) => $s->orderItem->title.' ×'.$s->quantity)->implode(', ') }}@if ($shipment->partner) · {{ $shipment->partner->name }}@endif</p>
                        <ol class="mt-2 space-y-0.5 border-l border-line pl-3 text-xs text-muted">
                            @foreach ($shipment->events as $event)
                                <li>{{ $event->occurred_at->translatedFormat('j M H:i') }} — {{ $event->status->label() }}@if ($event->location), {{ $event->location }}@endif @if ($event->notes): {{ $event->notes }}@endif</li>
                            @endforeach
                        </ol>
                        @unless (in_array($shipment->status->value, ['delivered', 'returned'], true))
                            <form method="post" action="{{ route('admin.shipments.events.store', $shipment) }}" class="mt-3 flex flex-wrap items-center gap-2">
                                @csrf
                                <select name="status" class="rounded-lg border border-line-strong py-1.5 pr-8 pl-3 text-xs">
                                    @foreach (\App\Enums\ShipmentStatus::cases() as $s)@if ($s->value !== 'dispatched')<option value="{{ $s->value }}">{{ $s->label() }}</option>@endif @endforeach
                                </select>
                                <input name="location" placeholder="{{ __('Location') }}" class="rounded-lg border border-line-strong px-2 py-1.5 text-xs">
                                <input name="notes" placeholder="{{ __('Notes') }}" class="rounded-lg border border-line-strong px-2 py-1.5 text-xs">
                                @if ($order->payment_method === 'cash_on_delivery' && ! $order->isPaid())
                                    <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="cash_collected" value="1" class="accent-brand-600"> {{ __('Cash collected') }}</label>
                                @endif
                                <x-ui.button size="sm" variant="secondary">{{ __('Record') }}</x-ui.button>
                            </form>
                        @endunless
                    </div>
                @empty
                    <p class="text-sm text-muted">{{ __('No shipments yet.') }}</p>
                @endforelse
            </x-ui.card>

            {{-- Audit --}}
            <x-ui.card :title="__('Audit trail')" :padding="false">
                <table class="table-base">
                    <tbody>
                        @forelse ($audit as $log)
                            <tr><td class="whitespace-nowrap text-xs text-muted">{{ $log->created_at->translatedFormat('j M H:i') }}</td><td class="text-xs font-medium">{{ $log->action }}</td><td class="text-xs text-muted">{{ $log->user?->name ?? __('system') }}</td><td class="max-w-xs truncate text-xs text-subtle">{{ $log->changes ? json_encode($log->changes) : '' }}</td></tr>
                        @empty
                            <tr><td class="text-sm text-muted">{{ __('No administrative actions recorded.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.card>
        </div>

        <aside class="space-y-6">
            <x-ui.card :title="__('Totals')">
                <dl class="space-y-1.5 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Subtotal') }}</dt><dd><x-ui.money :amount="$order->subtotal_amount" :currency="$cur" /></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Discount') }} @if ($order->discountCode)({{ $order->discountCode->code }})@endif</dt><dd>−<x-ui.money :amount="$order->discount_amount" :currency="$cur" /></dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Delivery') }} ({{ $order->deliveryZone->name }})</dt><dd><x-ui.money :amount="$order->delivery_amount" :currency="$cur" /></dd></div>
                    <div class="flex justify-between border-t border-line pt-1.5 font-semibold"><dt>{{ __('Total') }}</dt><dd><x-ui.money :amount="$order->total_amount" :currency="$cur" /></dd></div>
                    <div class="flex justify-between text-xs text-muted"><dt>{{ __('VAT included (:r%)', ['r' => $order->tax_rate_bps / 100]) }}</dt><dd><x-ui.money :amount="$order->tax_amount" :currency="$cur" /></dd></div>
                    <div class="flex justify-between text-brand-700"><dt>{{ __('Refunded') }}</dt><dd><x-ui.money :amount="$order->refunded_amount" :currency="$cur" /></dd></div>
                </dl>
            </x-ui.card>

            <x-ui.card :title="__('Customer & delivery')">
                <div class="space-y-2 text-sm">
                    <p>{{ $order->customer_name }}<br><span class="text-muted">{{ $order->email }} · {{ \App\Support\PhoneNumber::display($order->phone) }}</span></p>
                    @if ($order->user)<a href="{{ route('admin.users.show', $order->user) }}" class="link text-xs">{{ __('Customer account') }}</a>@else<p class="text-xs text-muted">{{ __('Guest checkout') }}</p>@endif
                    <p class="border-t border-line pt-2">{{ $order->address->recipient_name }}<br><span class="text-muted">{{ $order->address->summary() }}</span>@if ($order->address->landmark)<br><span class="text-xs text-muted">{{ $order->address->landmark }}</span>@endif</p>
                    @if ($order->customer_note)<p class="rounded-lg bg-sand px-3 py-2 text-xs">{{ $order->customer_note }}</p>@endif
                </div>
            </x-ui.card>

            <x-ui.card :title="__('Payments')" :padding="false">
                <ul class="divide-y divide-line text-sm">
                    @forelse ($order->payments as $payment)
                        <li class="p-4">
                            <div class="flex items-center justify-between"><span class="font-medium">{{ $payment->provider }}</span><x-ui.badge :status="$payment->status" /></div>
                            <p class="text-xs text-muted"><x-ui.money :amount="$payment->amount" :currency="$payment->currency" /> · {{ $payment->provider_reference ?? '—' }} · {{ $payment->created_at->translatedFormat('j M H:i') }}</p>
                            @if ($payment->failure_reason)<p class="text-xs text-danger-700">{{ $payment->failure_reason }}</p>@endif
                            <p class="text-xs text-subtle">{{ trans_choice(':count provider event|:count provider events', $payment->events->count(), ['count' => $payment->events->count()]) }}</p>
                            @if ($payment->status->value === 'pending' && $payment->provider !== 'cash_on_delivery')
                                @can('payments.view')<x-admin.inline-form :action="route('admin.payments.refresh', $payment)" :label="__('Check status with provider')" class="mt-2 block" />@endcan
                            @endif
                        </li>
                    @empty
                        <li class="p-4 text-muted">{{ __('No payment attempts.') }}</li>
                    @endforelse
                </ul>
            </x-ui.card>

            @can('refunds.manage')
                <x-ui.card :title="__('Refunds')">
                    @foreach ($order->refunds as $refund)
                        <div class="mb-4 rounded-xl border border-line p-3 text-sm">
                            <div class="flex items-center justify-between"><span class="font-medium">{{ $refund->reference }}</span><x-ui.badge :status="$refund->status" /></div>
                            <p class="text-xs text-muted"><x-ui.money :amount="$refund->amount" :currency="$cur" /> · {{ $refund->reason->label() }}@if ($refund->orderItem) · {{ $refund->orderItem->title }}@endif</p>
                            @if ($refund->customer_note)<p class="mt-1 text-xs">“{{ $refund->customer_note }}”</p>@endif
                            @include('admin.refunds.partials.actions', ['refund' => $refund])
                        </div>
                    @endforeach
                    @if ($maxRefund > 0)
                        <form method="post" action="{{ route('admin.refunds.store', $order) }}" class="space-y-3" onsubmit="return confirm(@js(__('Record this refund as paid to the customer?')))">
                            @csrf
                            <p class="text-sm font-semibold">{{ __('Issue a refund') }} <span class="font-normal text-muted">({{ __('max :amount', ['amount' => \App\Support\Money::format($maxRefund, $cur)]) }})</span></p>
                            <select name="order_item_id" class="input">
                                <option value="">{{ __('Whole order') }}</option>
                                @foreach ($order->items as $item)<option value="{{ $item->id }}">{{ $item->title }} — {{ $item->variantLabel() }}</option>@endforeach
                            </select>
                            <x-form.input name="amount" :label="__('Amount (:cur)', ['cur' => $cur])" inputmode="decimal" required />
                            <x-form.select name="reason" :label="__('Reason')" :options="\App\Enums\RefundReason::options()" />
                            <x-form.input name="provider_reference" :label="__('Mobile money / bank reference')" required :hint="__('Send the money first, then record the transfer reference.')" />
                            <x-ui.button size="sm" variant="danger" class="w-full">{{ __('Record refund') }}</x-ui.button>
                        </form>
                    @endif
                </x-ui.card>
            @endcan

            @if ($order->isAwaitingPayment())
                <x-ui.card :title="__('Cancel order')">
                    <form method="post" action="{{ route('admin.orders.cancel', $order) }}" class="space-y-3" onsubmit="return confirm(@js(__('Cancel this unpaid order?')))">
                        @csrf
                        <x-form.input name="reason" :label="__('Reason')" required />
                        <x-ui.button size="sm" variant="danger">{{ __('Cancel unpaid order') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </aside>
    </div>
</x-layouts.dashboard>
