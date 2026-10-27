<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\PayoutRequest;
use App\Models\User;

class PayoutRequestPolicy
{
    public function view(User $user, PayoutRequest $payout): bool
    {
        return $user->artistProfile?->id === $payout->artist_profile_id || $user->hasPermission(Ability::PayoutsManage) || $user->isSuperAdmin();
    }

    public function manage(User $user, PayoutRequest $payout): bool
    {
        return $user->hasPermission(Ability::PayoutsManage) || $user->isSuperAdmin();
    }
}
