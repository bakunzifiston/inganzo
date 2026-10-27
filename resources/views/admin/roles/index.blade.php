<x-layouts.dashboard :title="__('Roles & permissions')" area="admin">
    <x-ui.page-header :title="__('Roles & permissions')" :description="__('Staff access is checked on every request. Super administrators always have every permission. Changes are audited.')" />
    <div class="space-y-6">
        @foreach ($roles as $role)
            <x-ui.card :title="$role->name" :description="trans_choice(':count user|:count users', $role->users_count, ['count' => $role->users_count])">
                @if ($role->slug === 'super_admin')
                    <p class="text-sm text-muted">{{ __('Has every permission. Cannot be edited.') }}</p>
                @else
                    <form method="post" action="{{ route('admin.roles.update', $role) }}">
                        @csrf @method('put')
                        <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($permissions as $group => $items)
                                <fieldset>
                                    <legend class="text-xs font-semibold tracking-wide text-muted uppercase">{{ $group }}</legend>
                                    @foreach ($items as $permission)
                                        <label class="mt-1.5 flex items-start gap-2 text-sm"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains($permission)) class="mt-0.5 accent-brand-600"><span>{{ $permission->name }}</span></label>
                                    @endforeach
                                </fieldset>
                            @endforeach
                        </div>
                        <x-ui.button size="sm" class="mt-5">{{ __('Save :role', ['role' => $role->name]) }}</x-ui.button>
                    </form>
                @endif
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.dashboard>
