<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductionJobStatus;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\PrintingPartner;
use App\Models\ProductionJob;
use App\Services\Fulfillment\ProductionWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(ProductionJobStatus::class)], 'partner' => ['nullable', 'integer']]);

        $jobs = ProductionJob::query()
            ->with(['partner', 'orderItem.order'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')), fn ($q) => $request->boolean('all') ? $q : $q->active())
            ->when($request->filled('partner'), fn ($q) => $q->where('printing_partner_id', $request->integer('partner')))
            ->when($request->boolean('delayed'), fn ($q) => $q->delayed())
            ->when($request->boolean('issues'), fn ($q) => $q->where('has_issue', true))
            ->orderBy('due_at')
            ->paginate(30)->withQueryString();

        $counts = ProductionJob::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.production.index', [
            'jobs' => $jobs,
            'counts' => $counts,
            'delayedCount' => ProductionJob::query()->delayed()->count(),
            'partners' => PrintingPartner::query()->orderBy('name')->get(['id', 'name']),
            'unassigned' => OrderItem::query()->where('fulfillment_status', 'awaiting_assignment')->with('order')->latest('id')->limit(20)->get(),
        ]);
    }

    public function fail(Request $request, ProductionJob $job, ProductionWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255'], 'cost_incurred' => ['sometimes', 'boolean']]);
        $workflow->fail($job, $request->user(), $validated['reason'], $request->boolean('cost_incurred'));

        return back()->with('success', __('Job :ref marked as failed.', ['ref' => $job->reference]));
    }

    public function reprint(Request $request, ProductionJob $job, ProductionWorkflow $workflow): RedirectResponse
    {
        $validated = $request->validate(['printing_partner_id' => ['nullable', 'integer', Rule::exists('printing_partners', 'id')->where('is_active', true)]]);
        $reprint = $workflow->reprint($job, $request->user(), $validated['printing_partner_id'] ?? null);

        return back()->with('success', __('Reprint job :ref created.', ['ref' => $reprint->reference]));
    }
}
