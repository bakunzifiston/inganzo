<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArtworkStatus;
use App\Enums\CopyrightReportStatus;
use App\Http\Controllers\Controller;
use App\Models\CopyrightReport;
use App\Services\Artwork\ArtworkModeration;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CopyrightReportController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(CopyrightReportStatus::class)]]);

        return view('admin.copyright.index', [
            'reports' => CopyrightReport::query()->with('artwork.artist')
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')), fn ($q) => $q->whereIn('status', ['open', 'under_review']))
                ->latest()->paginate(25)->withQueryString(),
        ]);
    }

    public function show(CopyrightReport $report): View
    {
        if ($report->status === CopyrightReportStatus::Open) {
            $report->forceFill(['status' => CopyrightReportStatus::UnderReview])->save();
        }

        return view('admin.copyright.show', ['report' => $report->load(['artwork.artist', 'artwork.currentVersion', 'resolver'])]);
    }

    public function resolve(Request $request, CopyrightReport $report, ArtworkModeration $moderation, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['upheld', 'dismissed'])],
            'notes' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        abort_if(in_array($report->status, [CopyrightReportStatus::Upheld, CopyrightReportStatus::Dismissed], true), 422, __('This report is already resolved.'));

        if ($validated['decision'] === 'upheld' && $report->artwork->status !== ArtworkStatus::Suspended) {
            $moderation->suspend($report->artwork, $request->user(), __('Copyright takedown: :notes', ['notes' => $validated['notes']]));
        }

        $report->forceFill([
            'status' => CopyrightReportStatus::from($validated['decision']),
            'resolution_notes' => $validated['notes'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ])->save();

        $audit->log('copyright.'.$validated['decision'], $report, ['artwork_id' => $report->artwork_id, 'notes' => $validated['notes']]);

        return redirect()->route('admin.copyright.index')->with('success', $validated['decision'] === 'upheld' ? __('Report upheld and design taken down.') : __('Report dismissed.'));
    }
}
