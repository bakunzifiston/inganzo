<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\FinishedProduct;
use App\Models\User;

class FinishedProductPolicy
{
    /**
     * Listed products are public; everything else only to its artist and moderators.
     */
    public function view(?User $user, FinishedProduct $product): bool
    {
        if ($product->isListed()) {
            return true;
        }

        return $user !== null && ($this->owns($user, $product) || $user->hasPermission(Ability::ArtworksModerate) || $user->isSuperAdmin());
    }

    public function create(User $user): bool
    {
        return $user->isArtist() && in_array($user->artistProfile->status->value, ['pending', 'approved'], true);
    }

    /**
     * Artists manage only their own listings; taken-down listings are locked.
     */
    public function update(User $user, FinishedProduct $product): bool
    {
        return $this->owns($user, $product) && $product->status->isEditable();
    }

    public function manage(User $user, FinishedProduct $product): bool
    {
        return $this->owns($user, $product);
    }

    public function moderate(User $user, FinishedProduct $product): bool
    {
        return $user->hasPermission(Ability::ArtworksModerate) || $user->isSuperAdmin();
    }

    private function owns(User $user, FinishedProduct $product): bool
    {
        return $user->artistProfile !== null && $user->artistProfile->id === $product->artist_profile_id;
    }
}
