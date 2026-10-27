<?php

namespace App\Services\Finance;

use App\Enums\LedgerAccount;
use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Models\ArtistProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Str;

/**
 * Manual corrections are new adjustment entries; historical entries are never edited.
 */
class LedgerAdjuster
{
    public function __construct(private Ledger $ledger, private AuditLogger $audit) {}

    public function adjustArtist(ArtistProfile $artist, int $amount, string $currency, string $reason, User $actor): void
    {
        $key = 'adjustment:artist:'.$artist->id.':'.Str::uuid();

        $this->ledger->post([
            $this->ledger->entry($key, LedgerAccount::Artist, $artist->id, LedgerBucket::Available, LedgerEntryType::Adjustment, $amount, $currency, $reason, ['created_by' => $actor->id]),
            $this->ledger->entry($key.':platform', LedgerAccount::Platform, null, LedgerBucket::Revenue, LedgerEntryType::Adjustment, -$amount, $currency, $reason, ['created_by' => $actor->id]),
        ]);

        $this->audit->log('ledger.adjustment', $artist, ['amount' => $amount, 'currency' => $currency, 'reason' => $reason], $actor);
    }
}
