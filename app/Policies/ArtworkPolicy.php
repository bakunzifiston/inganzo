<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Artwork;
use App\Models\User;

class ArtworkPolicy
{
    /**
     * Public artwork is visible to everyone; unpublished artwork only to its artist and moderators.
     */
    public function view(?User $user, Artwork $artwork): bool
    {
        if ($artwork->isPublic()) {
            return true;
        }

        return $user !== null && ($this->owns($user, $artwork) || $user->hasPermission(Ability::ArtworksModerate) || $user->isSuperAdmin());
    }

    public function create(User $user): bool
    {
        return $user->isArtist() && in_array($user->artistProfile->status->value, ['pending', 'approved'], true);
    }

    public function update(User $user, Artwork $artwork): bool
    {
        return $this->owns($user, $artwork) && $artwork->isEditable();
    }

    public function submit(User $user, Artwork $artwork): bool
    {
        return $this->owns($user, $artwork);
    }

    public function archive(User $user, Artwork $artwork): bool
    {
        return $this->owns($user, $artwork);
    }

    public function moderate(User $user, Artwork $artwork): bool
    {
        return $user->hasPermission(Ability::ArtworksModerate) || $user->isSuperAdmin();
    }

    private function owns(User $user, Artwork $artwork): bool
    {
        return $user->artistProfile !== null && $user->artistProfile->id === $artwork->artist_profile_id;
    }
}
