<?php

namespace App\Models;

use App\Enums\Ability;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'locale', 'country_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var Collection<int, string>|null
     */
    private ?Collection $permissionCache = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot(['granted_by', 'created_at']);
    }

    /**
     * @return HasOne<ArtistProfile, $this>
     */
    public function artistProfile(): HasOne
    {
        return $this->hasOne(ArtistProfile::class);
    }

    /**
     * @return HasOne<PartnerMember, $this>
     */
    public function partnerMembership(): HasOne
    {
        return $this->hasOne(PartnerMember::class);
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return BelongsToMany<Artwork, $this>
     */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Artwork::class, 'favorites')->withPivot('created_at');
    }

    public function hasRole(RoleName ...$roles): bool
    {
        $slugs = array_map(fn (RoleName $role) => $role->value, $roles);

        return $this->roles->contains(fn (Role $role) => in_array($role->slug, $slugs, true));
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin);
    }

    public function isStaff(): bool
    {
        return $this->roles->contains('is_staff', true);
    }

    public function isArtist(): bool
    {
        return $this->hasRole(RoleName::Artist) && $this->artistProfile !== null;
    }

    public function isPartnerStaff(): bool
    {
        return $this->hasRole(RoleName::PartnerStaff) && $this->partnerMembership !== null;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function hasPermission(Ability|string $ability): bool
    {
        $slug = $ability instanceof Ability ? $ability->value : $ability;

        $this->permissionCache ??= $this->roles()
            ->with('permissions:id,slug')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('slug'))
            ->unique()
            ->values();

        return $this->permissionCache->contains($slug);
    }

    public function forgetPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    public function assignRole(RoleName $role, ?User $grantedBy = null): void
    {
        $roleModel = Role::query()->where('slug', $role->value)->firstOrFail();
        $this->roles()->syncWithoutDetaching([$roleModel->id => ['granted_by' => $grantedBy?->id]]);
        $this->forgetPermissionCache();
    }

    public function printingPartnerId(): ?int
    {
        return $this->partnerMembership?->printing_partner_id;
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->implode('');
    }
}
