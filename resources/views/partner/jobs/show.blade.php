@php
    use App\Enums\ProductionJobStatus;
    $product = $item->artistProduct?->product;
    $snapshot = collect($item->placement_snapshot ?? []);
    $views = $snapshot->map(fn ($p) => $p['print_area_key'] === 'back' ? 'back' : 'front')->unique()->values();
    $address = $order->address;
@endphp
<x-layouts.dashboard :title="__('Job :ref', ['ref' => $job->reference])" area="partner">
    <div class="mb-4 text-sm"><a href="{{ route('partner.dashboard') }}" class="text-muted hover:text-ink">← {{ __('All jobs') }}</a></div>

    <x-ui.page-header :eyebrow="__('Order :number', ['number' => $order->number])" :title="$job->reference">
        <x-slot:actions>
            <x-ui.badge :status="$job->status" />
            @if ($job->has_issue)<x-ui.badge tone="danger">{{ __('Issue reported') }}</x-ui.badge>@endif
            @if ($job->reprintOf)<x-ui.badge tone="accent">{{ __('Reprint of :ref', ['ref' => $job->reprintOf->reference]) }}</x-ui.badge>@endif
        </x-slot:actions>
    </x-ui.page-header>

    @if ($job->isDelayed())
        <div class="mb-6 rounded-xl border border-danger-700/20 bg-danger-50 px-4 py-3 text-sm text-danger-700">{{ __('This job was due :date and is overdue.', ['date' => $job->due_at->translatedFormat('j M Y, H:i')]) }}</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="space-y-6">
            <x-ui.card :title="__('Production specification')" :description="$item->product_name.' · '.$item->variantLabel().' · '.__('Quantity :qty', ['qty' => $job->quantity])">
                <div class="grid gap-6 md:grid-cols-[260px_1fr]">
                    <div class="space-y-3">
                        @foreach ($views as $view)
                            <div class="aspect-square rounded-xl bg-sand">
                                @if ($product && $item->artworkVersion)
                                    <x-shop.mockup :product="$product" :color="$item->variant->color" :version="$item->artworkVersion" :placements="$snapshot->all()" :view="$view" class="h-full w-full p-2" />
                                @endif
                            </div>
                            <p class="text-center text-xs text-muted">{{ $view === 'back' ? __('Back') : __('Front') }}</p>
                        @endforeach
                    </div>
                    <div class="space-y-5">
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div><dt class="text-muted">{{ __('SKU') }}</dt><dd class="font-medium">{{ $item->sku }}</dd></div>
                            <div><dt class="text-muted">{{ __('Garment colour') }}</dt><dd class="flex items-center gap-2 font-medium"><span class="size-4 rounded-full ring-1 ring-line-strong" style="background: {{ $item->color_hex }}"></span>{{ $item->color_name }}</dd></div>
                            <div><dt class="text-muted">{{ __('Size') }}</dt><dd class="font-medium">{{ $item->size_label ?? '—' }}</dd></div>
                            <div><dt class="text-muted">{{ __('Design') }}</dt><dd class="font-medium">{{ $item->title }}</dd></div>
                        </dl>
                        @foreach ($snapshot as $placement)
                            <div class="rounded-xl border border-line p-4">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-semibold">{{ __('Print area: :area', ['area' => $placement['print_area_label']]) }}</p>
                                    <x-ui.badge :status="\App\Enums\PrintCheckStatus::tryFrom($placement['print_check_status'])" />
                                </div>
                                <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                                    <div><dt class="text-muted">{{ __('Area size') }}</dt><dd>{{ $placement['print_area_mm'][0] }} × {{ $placement['print_area_mm'][1] }} mm</dd></div>
                                    <div><dt class="text-muted">{{ __('Printed width') }}</dt><dd>{{ $placement['printed_width_mm'] }} mm</dd></div>
                                    <div><dt class="text-muted">{{ __('Effective DPI') }}</dt><dd>{{ $placement['effective_dpi'] }}</dd></div>
                                    <div><dt class="text-muted">{{ __('Offset from left') }}</dt><dd>{{ round($placement['offset_x'] * $placement['print_area_mm'][0], 1) }} mm</dd></div>
                                    <div><dt class="text-muted">{{ __('Offset from top') }}</dt><dd>{{ round($placement['offset_y'] * $placement['print_area_mm'][1], 1) }} mm</dd></div>
                                    <div><dt class="text-muted">{{ __('Rotation') }}</dt><dd>{{ $placement['rotation'] }}°</dd></div>
                                </dl>
                            </div>
                        @endforeach
                        @if ($item->artworkVersion)
                            <div class="flex flex-wrap items-center gap-3 rounded-xl bg-sand/60 p-4 text-sm">
                                <x-ui.icon name="image" class="size-5 text-muted" />
                                <div class="flex-1">
                                    <p class="font-medium">{{ __('Original artwork (version :v)', ['v' => $item->artworkVersion->version_number]) }}</p>
                                    <p class="text-xs text-muted">{{ $item->artworkVersion->width }} × {{ $item->artworkVersion->height }} px · {{ $item->artworkVersion->mime_type }} · SHA-256 {{ substr($item->artworkVersion->sha256, 0, 12) }}…</p>
                                </div>
                                <x-ui.button :href="route('media.original', $item->artworkVersion)" variant="secondary" size="sm"><x-ui.icon name="download" class="size-4" /> {{ __('Download') }}</x-ui.button>
                            </div>
                        @endif
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card :title="__('History')">
                @if ($job->events->isEmpty())
                    <p class="text-sm text-muted">{{ __('No events yet.') }}</p>
                @else
                    <ol class="space-y-4 border-l border-line pl-5">
                        @foreach ($job->events as $event)
                            <li class="relative text-sm">
                                <span class="absolute top-1.5 -left-[25px] size-2.5 rounded-full {{ $event->type === 'issue' ? 'bg-danger-700' : ($loop->first ? 'bg-success-600' : 'bg-line-strong') }}"></span>
                                <p class="font-medium">
                                    @if ($event->to_status)
                                        {{ \App\Enums\ProductionJobStatus::tryFrom($event->to_status)?->label() ?? $event->to_status }}
                                    @else
                                        {{ $event->type === 'issue' ? __('Issue reported') : ucfirst($event->type) }}
                                    @endif
                                </p>
                                @if ($event->notes)<p class="text-muted">{{ $event->notes }}</p>@endif
                                <p class="text-xs text-subtle">{{ $event->created_at->translatedFormat('j M Y, H:i') }}@if ($event->user) · {{ $event->user->name }}@endif</p>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-ui.card>
        </div>

        <aside class="space-y-6">
            <x-ui.card :title="__('Next step')">
                <dl class="mb-4 space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Assigned') }}</dt><dd>{{ $job->assigned_at->translatedFormat('j M, H:i') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted">{{ __('Due') }}</dt><dd @class(['font-semibold text-danger-700' => $job->isDelayed()])>{{ $job->due_at->translatedFormat('j M, H:i') }}</dd></div>
                </dl>

                @foreach ($job->status->partnerTransitions() as $next)
                    <form method="post" action="{{ route('partner.jobs.advance', $job) }}" class="mb-2">
                        @csrf
                        <input type="hidden" name="status" value="{{ $next->value }}">
                        <x-ui.button variant="accent" class="w-full">
                            {{ match ($next) { ProductionJobStatus::Accepted => __('Accept job'), ProductionJobStatus::InProduction => __('Start printing'), ProductionJobStatus::QualityCheck => __('Send to quality check'), default => $next->label() } }}
                        </x-ui.button>
                    </form>
                @endforeach

                @if ($job->status === ProductionJobStatus::QualityCheck)
                    <form method="post" action="{{ route('partner.jobs.qc', $job) }}" class="space-y-3" x-data="{ result: 'passed' }">
                        @csrf
                        <fieldset>
                            <legend class="label">{{ __('Quality check result') }}</legend>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border p-2.5 text-sm" :class="result === 'passed' ? 'border-success-600 bg-success-50' : 'border-line'">
                                    <input type="radio" name="qc_result" value="passed" x-model="result" class="accent-success-600"> {{ __('Passed') }}
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border p-2.5 text-sm" :class="result === 'failed' ? 'border-danger-700 bg-danger-50' : 'border-line'">
                                    <input type="radio" name="qc_result" value="failed" x-model="result" class="accent-danger-700"> {{ __('Failed') }}
                                </label>
                            </div>
                        </fieldset>
                        <x-form.textarea name="qc_notes" :label="__('Notes')" rows="2" :hint="__('Required when the check fails.')" />
                        <x-ui.button class="w-full">{{ __('Record quality check') }}</x-ui.button>
                    </form>
                @endif

                @if ($job->status === ProductionJobStatus::ReadyForDispatch)
                    <form method="post" action="{{ route('partner.shipments.store', $order) }}" class="space-y-3">
                        @csrf
                        <fieldset>
                            <legend class="label">{{ __('Items to ship') }}</legend>
                            @foreach ($readyItems as $ready)
                                <label class="flex items-center gap-2 py-1 text-sm">
                                    <input type="checkbox" name="items[]" value="{{ $ready->id }}" checked class="accent-brand-600">
                                    {{ $ready->product_name }} · {{ $ready->variantLabel() }} × {{ $ready->quantity }}
                                </label>
                            @endforeach
                        </fieldset>
                        <x-form.input name="carrier" :label="__('Carrier')" required placeholder="Kigali Express" />
                        <x-form.input name="tracking_number" :label="__('Tracking number')" />
                        <x-form.input name="tracking_url" type="url" :label="__('Tracking link (optional)')" />
                        <x-ui.button variant="accent" class="w-full"><x-ui.icon name="truck" class="size-4" /> {{ __('Dispatch shipment') }}</x-ui.button>
                    </form>
                @endif

                @if (! $job->status->isActive())
                    <p class="text-sm text-muted">{{ __('No further production steps for this job.') }}</p>
                @endif
            </x-ui.card>

            <x-ui.card :title="__('Deliver to')">
                <p class="text-sm">
                    <span class="font-medium">{{ $address->recipient_name }}</span><br>
                    {{ $address->summary() }}<br>
                    @if ($address->landmark)<span class="text-muted">{{ $address->landmark }}</span><br>@endif
                    <a href="tel:{{ $address->phone }}" class="link">{{ \App\Support\PhoneNumber::display($address->phone) }}</a>
                </p>
                @if ($order->customer_note)
                    <p class="mt-3 rounded-lg bg-sand px-3 py-2 text-xs">{{ $order->customer_note }}</p>
                @endif
                @if ($order->payment_method === 'cash_on_delivery' && ! $order->isPaid())
                    <p class="mt-3 rounded-lg bg-warning-50 px-3 py-2 text-xs text-warning-700">{{ __('Cash on delivery: the courier must collect payment.') }}</p>
                @endif
            </x-ui.card>

            @if ($job->status->isActive())
                <x-ui.card :title="__('Problems')">
                    <form method="post" action="{{ route('partner.jobs.issue', $job) }}" class="space-y-3">
                        @csrf
                        <x-form.textarea name="notes" :label="__('Report an issue or delay')" rows="2" required />
                        <x-ui.button variant="secondary" class="w-full">{{ __('Report issue') }}</x-ui.button>
                    </form>
                    <form method="post" action="{{ route('partner.jobs.fail', $job) }}" class="mt-5 space-y-3 border-t border-line pt-5" x-data @submit="if (! confirm(@js(__('Mark this job as failed? Operations will arrange a reprint.')))) $event.preventDefault()">
                        @csrf
                        <x-form.input name="reason" :label="__('Cannot complete this job')" :placeholder="__('Blank out of stock, machine failure…')" required />
                        <x-ui.button variant="danger" class="w-full">{{ __('Mark as failed') }}</x-ui.button>
                    </form>
                </x-ui.card>
            @endif
        </aside>
    </div>
</x-layouts.dashboard>
