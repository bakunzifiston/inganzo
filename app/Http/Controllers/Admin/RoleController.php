<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->where('is_staff', true)->with('permissions')->withCount('users')->orderBy('id')->get(),
            'permissions' => Permission::query()->orderBy('group')->orderBy('slug')->get()->groupBy('group'),
        ]);
    }

    public function update(Request $request, Role $role, AuditLogger $audit): RedirectResponse
    {
        abort_unless($role->is_staff, 404);

        if ($role->slug === RoleName::SuperAdmin->value) {
            return back()->with('error', __('Super administrators always have every permission.'));
        }

        $validated = $request->validate(['permissions' => ['array'], 'permissions.*' => ['exists:permissions,id']]);
        $before = $role->permissions->pluck('slug')->all();
        $role->permissions()->sync($validated['permissions'] ?? []);
        $after = $role->permissions()->pluck('slug')->all();

        $audit->log('role.permissions_changed', $role, ['added' => array_values(array_diff($after, $before)), 'removed' => array_values(array_diff($before, $after))]);

        return back()->with('success', __('Permissions for :role saved.', ['role' => $role->name]));
    }
}
