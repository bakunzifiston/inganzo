<x-layouts.dashboard :title="__('Shipments')" area="partner">
    <x-ui.page-header :title="__('Shipments')" :description="__('Dispatch finished items and keep customers updated until delivery.')" />

    @if ($readyGroups->isNotEmpty())
        <section class="mb-10">
            <h2 class="mb-3 text-lg font-semibold">{{ __('Ready to ship') }}</h2>
            <div class="grid gap-4 lg:grid-cols-2">
                @foreach ($readyGroups as $items)
                    @php $order = $items->first()->order; @endphp
                    <form method="post" action="{{ route('partner.shipments.store', $order) }}" class="card space-y-4 p-5">
                        @csrf
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ __('Order :number', ['number' => $order->number]) }}</p>
                                <p class="text-xs text-muted">{{ $order->address->recipient_name }} · {{ $order->address->district }}</p>
                            </div>
                            @if ($order->payment_method === 'cash_on_delivery' && ! $order->isPaid())<x-ui.badge tone="warning">{{ __('Cash on delivery') }}</x-ui.badge>@endif
                        </div>
                        <div class="space-y-1">
                            @foreach ($items as $ready)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="items[]" value="{{ $ready->id }}" checked class="accent-brand-600">
                                    <span>{{ $ready->product_name }} · {{ $ready->variantLabel() }} × {{ $ready->quantity }}</span>
                                    <span class="ml-auto text-xs text-subtle">{{ $ready->activeJob->reference }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <x-form.input name="carrier" :label="__('Carrier')" required placeholder="Kigali Express" :id="'carrier-'.$order->id" />
                            <x-form.input name="tracking_number" :label="__('Tracking number')" :id="'tracking-'.$order->id" />
                        </div>
                        <x-form.input name="tracking_url" type="url" :label="__('Tracking link (optional)')" :id="'url-'.$order->id" />
                        <x-ui.button variant="accent" size="sm"><x-ui.icon name="truck" class="size-4" /> {{ __('Dispatch') }}</x-ui.button>
                    </form>
                @endforeach
            </div>
        </section>
    @endif

    <section>
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">{{ __('Shipment history') }}</h2>
            <form method="get" class="flex items-center gap-2 text-sm">
                <label for="status-filter" class="text-muted">{{ __('Status') }}</label>
                <select id="status-filter" name="status" onchange="this.form.submit()" class="rounded-lg border border-line-strong bg-white py-1.5 pr-8 pl-3">
                    <option value="">{{ __('All') }}</option>
                    @foreach (\App\Enums\ShipmentStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </form>
        </div>

        @if ($shipments->isEmpty())
            <x-ui.empty icon="truck" :title="__('No shipments yet')" :description="__('Shipments you dispatch will appear here.')" />
        @else
            <div class="space-y-4">
                @foreach ($shipments as $shipment)
                    <article class="card p-5" x-data="{ open: false }">
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="flex-1">
                                <p class="font-semibold">{{ $shipment->reference }} · {{ __('Order :number', ['number' => $shipment->order->number]) }}</p>
                                <p class="text-xs text-muted">{{ $shipment->carrier }}@if ($shipment->tracking_number) · #{{ $shipment->tracking_number }}@endif · {{ $shipment->order->address->recipient_name }}, {{ $shipment->order->address->district }} · {{ \App\Support\PhoneNumber::display($shipment->order->address->phone) }}</p>
                            </div>
                            <x-ui.badge :status="$shipment->status" />
                            @if (! in_array($shipment->status, [\App\Enums\ShipmentStatus::Delivered, \App\Enums\ShipmentStatus::Returned], true))
                                <x-ui.button type="button" variant="secondary" size="sm" @click="open = ! open">{{ __('Update') }}</x-ui.button>
                            @endif
                        </div>
                        <p class="mt-2 text-xs text-muted">
                            @foreach ($shipment->items as $line){{ $line->orderItem->product_name }} × {{ $line->quantity }}@if (! $loop->last), @endif @endforeach
                        </p>

                        <form x-cloak x-show="open" method="post" action="{{ route('partner.shipments.events.store', $shipment) }}" class="mt-4 grid gap-3 border-t border-line pt-4 sm:grid-cols-3" x-data="{ status: 'in_transit' }">
                            @csrf
                            <x-form.field name="status" :label="__('New status')">
                                <select name="status" x-model="status" class="input">
                                    @foreach ($eventStatuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                                </select>
                            </x-form.field>
                            <x-form.input name="location" :label="__('Location')" :id="'loc-'.$shipment->id" />
                            <x-form.input name="notes" :label="__('Notes')" :id="'notes-'.$shipment->id" />
                            @if ($shipment->order->payment_method === 'cash_on_delivery' && ! $shipment->order->isPaid())
                                <div x-show="status === 'delivered'" class="sm:col-span-3">
                                    <x-form.checkbox name="cash_collected" :label="__('Cash collected: :amount', ['amount' => \App\Support\Money::format($shipment->order->total_amount, $shipment->order->currency)])" />
                                </div>
                            @endif
                            <div class="sm:col-span-3"><x-ui.button size="sm">{{ __('Save update') }}</x-ui.button></div>
                        </form>

                        @if ($shipment->events->isNotEmpty())
                            <ol class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-xs text-muted">
                                @foreach ($shipment->events->reverse() as $event)
                                    <li><span class="font-medium text-ink">{{ $event->status->label() }}</span> · {{ $event->occurred_at->translatedFormat('j M H:i') }}@if ($event->location) · {{ $event->location }}@endif</li>
                                @endforeach
                            </ol>
                        @endif
                    </article>
                @endforeach
            </div>
            <div class="mt-6">{{ $shipments->links() }}</div>
        @endif
    </section>
</x-layouts.dashboard>
