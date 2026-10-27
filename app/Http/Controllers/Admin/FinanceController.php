<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Http\Controllers\Controller;
use App\Models\ArtistProfile;
use App\Models\LedgerEntry;
use App\Models\PrintingPartner;
use App\Services\Finance\LedgerAdjuster;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function index(Request $request): View
    {
        $currency = 'RWF';
        $totals = LedgerEntry::query()->where('currency', $currency)
            ->selectRaw('account_type, bucket, SUM(amount) AS total')->groupBy('account_type', 'bucket')->get()
            ->mapWithKeys(fn ($row) => [$row->account_type->value.'.'.$row->bucket->value => (int) $row->total]);

        $byType = LedgerEntry::query()->where('currency', $currency)->where('account_type', LedgerAccount::Platform)
            ->selectRaw('type, SUM(amount) AS total')->groupBy('type')->pluck('total', 'type');

        $partnerIds = LedgerEntry::query()->where('account_type', LedgerAccount::Partner)->select('account_id', DB::raw('SUM(amount) AS total'))->groupBy('account_id')->pluck('total', 'account_id');

        return view('admin.finance.index', [
            'currency' => $currency,
            'totals' => $totals,
            'platformByType' => $byType,
            'partners' => PrintingPartner::query()->whereIn('id', $partnerIds->keys())->get()->map(fn ($p) => ['partner' => $p, 'payable' => (int) $partnerIds[$p->id]]),
            'artists' => ArtistProfile::query()
                ->joinSub(LedgerEntry::query()->where('account_type', LedgerAccount::Artist)->selectRaw("account_id, SUM(CASE WHEN bucket = 'held' THEN amount ELSE 0 END) AS held, SUM(CASE WHEN bucket = 'available' THEN amount ELSE 0 END) AS available")->groupBy('account_id'), 'balances', 'balances.account_id', '=', 'artist_profiles.id')
                ->select('artist_profiles.*', 'balances.held', 'balances.available')
                ->orderByDesc('balances.available')->limit(50)->get(),
            'entries' => $this->filtered($request)->with('order')->latest('id')->paginate(40)->withQueryString(),
            'allArtists' => ArtistProfile::query()->orderBy('display_name')->pluck('display_name', 'id'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('order')->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'created_at', 'entry_key', 'account_type', 'account_id', 'bucket', 'type', 'amount', 'currency', 'order', 'order_item_id', 'refund_id', 'payout_request_id', 'production_job_id', 'description']);
            $query->chunk(1000, function ($entries) use ($out) {
                foreach ($entries as $e) {
                    fputcsv($out, [$e->id, $e->created_at->toIso8601String(), $e->entry_key, $e->account_type->value, $e->account_id, $e->bucket->value, $e->type->value, $e->amount, $e->currency, $e->order?->number, $e->order_item_id, $e->refund_id, $e->payout_request_id, $e->production_job_id, $e->description]);
                }
            });
            fclose($out);
        }, 'ledger-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function adjust(Request $request, LedgerAdjuster $adjuster): RedirectResponse
    {
        abort_unless($request->user()->can('finance.manage'), 403);

        $validated = $request->validate([
            'artist_profile_id' => ['required', 'integer', 'exists:artist_profiles,id'],
            'direction' => ['required', Rule::in(['credit', 'debit'])],
            'amount' => ['required', 'string', 'max:20'],
            'reason' => ['required', 'string', 'min:10', 'max:255'],
        ]);

        $amount = Money::toMinor($validated['amount'], 'RWF');
        abort_if($amount <= 0, 422);

        $adjuster->adjustArtist(ArtistProfile::query()->findOrFail($validated['artist_profile_id']), $validated['direction'] === 'debit' ? -$amount : $amount, 'RWF', $validated['reason'], $request->user());

        return back()->with('success', __('Adjustment posted to the ledger.'));
    }

    /**
     * @return Builder<LedgerEntry>
     */
    private function filtered(Request $request): Builder
    {
        $request->validate([
            'account_type' => ['nullable', Rule::enum(LedgerAccount::class)],
            'bucket' => ['nullable', Rule::enum(LedgerBucket::class)],
            'type' => ['nullable', Rule::enum(LedgerEntryType::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return LedgerEntry::query()
            ->when($request->filled('account_type'), fn ($q) => $q->where('account_type', $request->string('account_type')))
            ->when($request->filled('account_id'), fn ($q) => $q->where('account_id', $request->integer('account_id')))
            ->when($request->filled('bucket'), fn ($q) => $q->where('bucket', $request->string('bucket')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()));
    }
}
