<?php

namespace App\Http\Controllers\Partner;

use App\Enums\ProductionJobStatus;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Services\Fulfillment\ShipmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ShipmentController extends Controller
{
    public function __construct(private ShipmentService $shipments) {}

    public function index(Request $request): View
    {
        $partnerId = $request->user()->printingPartnerId();

        $readyItems = OrderItem::query()
            ->whereHas('activeJob', fn (Builder $q) => $q->where('printing_partner_id', $partnerId)->where('status', ProductionJobStatus::ReadyForDispatch))
            ->with(['order.address', 'activeJob'])
            ->orderBy('order_id')
            ->get()
            ->groupBy('order_id');

        $shipments = Shipment::query()
            ->where('printing_partner_id', $partnerId)
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->with(['order.address', 'items.orderItem', 'events'])
            ->latest('dispatched_at')
            ->paginate(20)
            ->withQueryString();

        return view('partner.shipments.index', [
            'readyGroups' => $readyItems,
            'shipments' => $shipments,
            'eventStatuses' => collect(ShipmentStatus::cases())->reject(fn ($s) => $s === ShipmentStatus::Dispatched)->values(),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $partnerId = $request->user()->printingPartnerId();

        $hasJobs = $order->items()->whereHas('productionJobs', fn (Builder $q) => $q->where('printing_partner_id', $partnerId))->exists();
        abort_unless($hasJobs, 404);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
            'carrier' => ['required', 'string', 'max:80'],
            'tracking_number' => ['nullable', 'string', 'max:80'],
            'tracking_url' => ['nullable', 'url', 'max:255'],
        ]);

        $shipment = $this->shipments->dispatch(
            $order,
            array_map('intval', $validated['items']),
            $validated['carrier'],
            $validated['tracking_number'] ?? null,
            $validated['tracking_url'] ?? null,
            $request->user(),
            $partnerId,
        );

        return redirect()->route('partner.shipments.index')->with('success', __('Shipment :ref dispatched. The customer has been notified.', ['ref' => $shipment->reference]));
    }

    public function event(Request $request, Shipment $shipment): RedirectResponse
    {
        abort_unless($shipment->printing_partner_id === $request->user()->printingPartnerId(), 404);
        Gate::authorize('update', $shipment);

        $allowed = collect(ShipmentStatus::cases())->reject(fn ($s) => $s === ShipmentStatus::Dispatched)->map->value->all();

        $validated = $request->validate([
            'status' => ['required', Rule::in($allowed)],
            'location' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
            'cash_collected' => ['sometimes', 'boolean'],
        ]);

        $this->shipments->recordEvent(
            $shipment,
            ShipmentStatus::from($validated['status']),
            $request->user(),
            $validated['location'] ?? null,
            $validated['notes'] ?? null,
            $request->boolean('cash_collected'),
        );

        return back()->with('success', __('Shipment :ref updated.', ['ref' => $shipment->reference]));
    }
}
