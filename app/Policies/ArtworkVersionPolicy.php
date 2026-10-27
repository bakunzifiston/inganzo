<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\ArtworkVersion;
use App\Models\ProductionJob;
use App\Models\User;

class ArtworkVersionPolicy
{
    /**
     * Web previews follow artwork visibility.
     */
    public function viewPreview(?User $user, ArtworkVersion $version): bool
    {
        return (new ArtworkPolicy)->view($user, $version->artwork);
    }

    /**
     * Full-resolution production files: the artist, moderators/order managers, and partners
     * holding a job that prints this exact version.
     */
    public function downloadOriginal(User $user, ArtworkVersion $version): bool
    {
        if ($user->artistProfile?->id === $version->artwork->artist_profile_id) {
            return true;
        }

        if ($user->hasPermission(Ability::ArtworksModerate) || $user->hasPermission(Ability::OrdersManage) || $user->isSuperAdmin()) {
            return true;
        }

        return $user->isPartnerStaff() && ProductionJob::query()
            ->where('printing_partner_id', $user->printingPartnerId())
            ->whereHas('orderItem', fn ($q) => $q->where('artwork_version_id', $version->id))
            ->exists();
    }
}
