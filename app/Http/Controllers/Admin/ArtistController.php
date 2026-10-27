<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ArtistStatus;
use App\Enums\LedgerBucket;
use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use App\Services\AuditLogger;
use App\Services\Finance\Ledger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArtistController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(ArtistStatus::class)]]);

        return view('admin.artists.index', [
            'artists' => ArtistProfile::query()->with('user')
                ->withCount(['artworks', 'publishedArtworks'])
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('display_name', 'ilike', '%'.$request->string('q').'%')
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'ilike', '%'.$request->string('q').'%'))))
                ->orderByRaw("CASE status WHEN 'pending' THEN 0 ELSE 1 END")->latest()
                ->paginate(25)->withQueryString(),
        ]);
    }

    public function show(ArtistProfile $artistProfile, Ledger $ledger): View
    {
        $artistProfile->load(['user', 'payoutAccounts', 'payoutRequests' => fn ($q) => $q->latest()->limit(10)]);

        return view('admin.artists.show', [
            'artist' => $artistProfile,
            'artworks' => $artistProfile->artworks()->with('currentVersion')->latest()->get(),
            'held' => $ledger->artistBalance($artistProfile->id, LedgerBucket::Held),
            'available' => $ledger->artistBalance($artistProfile->id, LedgerBucket::Available),
            'lifetime' => (int) $artistProfile->ledgerEntries()->where('type', 'sale_earning')->sum('amount'),
            'entries' => $artistProfile->ledgerEntries()->with('order')->latest('id')->limit(25)->get(),
            'unitsSold' => (int) $artistProfile->orderItems()->whereHas('order', fn ($q) => $q->countableSales())->sum('quantity'),
        ]);
    }

    public function status(Request $request, ArtistProfile $artistProfile, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ArtistStatus::class)],
            'reason' => [Rule::requiredIf(in_array($request->input('status'), ['rejected', 'suspended'], true)), 'nullable', 'string', 'max:1000'],
        ]);

        $status = ArtistStatus::from($validated['status']);
        $old = $artistProfile->status;

        $artistProfile->status = $status;
        $artistProfile->status_reason = $validated['reason'] ?? null;
        if ($status === ArtistStatus::Approved && $artistProfile->approved_at === null) {
            $artistProfile->approved_at = now();
            $artistProfile->approved_by = $request->user()->id;
        }
        $artistProfile->save();

        $audit->log('artist.status_changed', $artistProfile, ['old' => $old->value, 'new' => $status->value, 'reason' => $validated['reason'] ?? null]);

        return back()->with('success', __('Artist status changed to :status.', ['status' => $status->label()]));
    }

    public function feature(ArtistProfile $artistProfile, AuditLogger $audit): RedirectResponse
    {
        $artistProfile->is_featured = ! $artistProfile->is_featured;
        $artistProfile->save();
        $audit->log('artist.featured', $artistProfile, ['featured' => $artistProfile->is_featured]);

        return back()->with('success', $artistProfile->is_featured ? __('Artist featured on the homepage.') : __('Artist removed from featured.'));
    }
}
