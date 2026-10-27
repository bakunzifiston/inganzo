<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'users' => User::query()->with('roles')
                ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'ilike', '%'.$request->string('q').'%')->orWhere('email', 'ilike', '%'.$request->string('q').'%')))
                ->when($request->filled('role'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->where('slug', $request->string('role'))))
                ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
                ->withCount('orders')->latest()->paginate(30)->withQueryString(),
            'roles' => Role::query()->orderBy('id')->pluck('name', 'slug'),
        ]);
    }

    public function show(User $user): View
    {
        return view('admin.users.show', [
            'user' => $user->load(['roles', 'artistProfile', 'partnerMembership.partner', 'addresses.district']),
            'orders' => $user->orders()->latest('placed_at')->limit(10)->get(),
            'roles' => Role::query()->orderBy('id')->get(),
            'activity' => AuditLog::query()->where('user_id', $user->id)->latest('created_at')->limit(15)->get(),
        ]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        if ($user->is($request->user()) || ($user->isSuperAdmin() && ! $request->user()->isSuperAdmin())) {
            return back()->with('error', __('You cannot suspend this account.'));
        }

        $user->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => now(), 'suspension_reason' => $validated['reason']])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
        $this->audit->log('user.suspended', $user, ['reason' => $validated['reason']]);

        return back()->with('success', __('Account suspended and signed out everywhere.'));
    }

    public function reactivate(User $user): RedirectResponse
    {
        $user->forceFill(['status' => UserStatus::Active, 'suspended_at' => null, 'suspension_reason' => null])->save();
        $this->audit->log('user.reactivated', $user);

        return back()->with('success', __('Account reactivated.'));
    }

    public function roles(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['roles' => ['array'], 'roles.*' => [Rule::exists('roles', 'id')]]);
        $actor = $request->user();
        $requested = Role::query()->whereIn('id', $validated['roles'] ?? [])->get();
        $superAdmin = Role::query()->where('slug', RoleName::SuperAdmin->value)->first();

        // Only super administrators may grant or revoke super administrator.
        $hadSuper = $user->hasRole(RoleName::SuperAdmin);
        $wantsSuper = $requested->contains('id', $superAdmin?->id);
        if ($hadSuper !== $wantsSuper && ! $actor->isSuperAdmin()) {
            return back()->with('error', __('Only a super administrator can change super administrator access.'));
        }
        if ($user->is($actor) && $hadSuper && ! $wantsSuper) {
            return back()->with('error', __('You cannot remove your own super administrator role.'));
        }
        if ($hadSuper && ! $wantsSuper && User::query()->whereHas('roles', fn ($q) => $q->where('slug', RoleName::SuperAdmin->value))->count() <= 1) {
            return back()->with('error', __('At least one super administrator must remain.'));
        }

        $before = $user->roles->pluck('slug')->all();
        $user->roles()->sync($requested->mapWithKeys(fn ($role) => [$role->id => ['granted_by' => $actor->id]])->all());
        $user->forgetPermissionCache();
        $this->audit->log('user.roles_changed', $user, ['old' => $before, 'new' => $requested->pluck('slug')->all()]);

        return back()->with('success', __('Roles updated.'));
    }
}
