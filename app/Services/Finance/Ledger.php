<?php

namespace App\Services\Finance;

use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Models\LedgerEntry;

/**
 * Writes append-only ledger entries. Each entry has a deterministic entry_key, and inserts
 * use ON CONFLICT DO NOTHING so replays of the same business event never double-post.
 */
class Ledger
{
    /**
     * @param  array{order_id?: int|null, order_item_id?: int|null, refund_id?: int|null, payout_request_id?: int|null, production_job_id?: int|null, created_by?: int|null}  $references
     */
    public function entry(
        string $key,
        LedgerAccount $account,
        ?int $accountId,
        LedgerBucket $bucket,
        LedgerEntryType $type,
        int $amount,
        string $currency,
        string $description,
        array $references = [],
    ): array {
        return array_merge([
            'order_id' => null,
            'order_item_id' => null,
            'refund_id' => null,
            'payout_request_id' => null,
            'production_job_id' => null,
            'created_by' => null,
        ], $references, [
            'entry_key' => $key,
            'account_type' => $account->value,
            'account_id' => $accountId,
            'bucket' => $bucket->value,
            'type' => $type->value,
            'amount' => $amount,
            'currency' => $currency,
            'description' => mb_substr($description, 0, 255),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return int number of entries actually written (0 when all were already posted)
     */
    public function post(array $entries): int
    {
        $entries = array_values(array_filter($entries, fn (array $entry) => $entry['amount'] !== 0));

        if ($entries === []) {
            return 0;
        }

        return LedgerEntry::query()->insertOrIgnore($entries);
    }

    public function exists(string $key): bool
    {
        return LedgerEntry::query()->where('entry_key', $key)->exists();
    }

    public function artistBalance(int $artistProfileId, LedgerBucket $bucket, ?string $currency = null): int
    {
        return (int) LedgerEntry::query()
            ->where('account_type', LedgerAccount::Artist)
            ->where('account_id', $artistProfileId)
            ->where('bucket', $bucket)
            ->when($currency, fn ($q) => $q->where('currency', $currency))
            ->sum('amount');
    }

    /**
     * Artist earnings still attributable to an order item, split by bucket.
     *
     * @return array{held: int, available: int}
     */
    public function itemArtistBalances(int $orderItemId): array
    {
        $rows = LedgerEntry::query()
            ->where('account_type', LedgerAccount::Artist)
            ->where('order_item_id', $orderItemId)
            ->selectRaw('bucket, SUM(amount) AS total')
            ->groupBy('bucket')
            ->pluck('total', 'bucket');

        return [
            'held' => (int) ($rows[LedgerBucket::Held->value] ?? 0),
            'available' => (int) ($rows[LedgerBucket::Available->value] ?? 0),
        ];
    }
}
