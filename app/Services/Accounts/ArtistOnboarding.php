<?php

namespace App\Services\Accounts;

use App\Enums\ArtistStatus;
use App\Enums\RoleName;
use App\Models\ArtistProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates an artist profile (pending administrator approval) for a user.
 */
class ArtistOnboarding
{
    public function start(User $user, string $displayName): ArtistProfile
    {
        return DB::transaction(function () use ($user, $displayName) {
            $profile = $user->artistProfile ?? new ArtistProfile(['user_id' => $user->id]);
            $profile->fill([
                'display_name' => $displayName,
                'slug' => $profile->slug ?? $this->uniqueSlug($displayName),
                'agreement_accepted_at' => now(),
                'agreement_version' => config('marketplace.agreement_version'),
            ]);
            $profile->status ??= ArtistStatus::Pending;
            $profile->save();

            $user->assignRole(RoleName::Artist);
            $user->setRelation('artistProfile', $profile);

            return $profile;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'artist';
        $slug = $base;
        $i = 2;

        while (ArtistProfile::query()->where('slug', $slug)->exists() || in_array($slug, ['create', 'edit'], true)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
