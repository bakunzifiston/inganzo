<?php

namespace App\Console\Commands;

use App\Services\Finance\EarningsReleaser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('earnings:release')]
#[Description('Move artist earnings from held to available once delivered and past the hold period')]
class ReleaseEarnings extends Command
{
    public function handle(EarningsReleaser $releaser): int
    {
        $this->info('Released earnings for '.$releaser->releaseEligible().' order items.');

        return self::SUCCESS;
    }
}
