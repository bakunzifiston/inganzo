<x-layouts.dashboard :title="__('Profile & security')" area="account">
    <x-ui.page-header :title="__('Profile & security')" />
    <div class="grid max-w-4xl gap-6 lg:grid-cols-2">
        <x-ui.card :title="__('Profile')">
            <form method="post" action="{{ route('account.profile.update') }}" class="space-y-5">
                @csrf @method('put')
                <x-form.input name="name" :label="__('Full name')" :value="$user->name" required />
                <x-form.field name="email" :label="__('E-mail')">
                    <input id="email" class="input" value="{{ $user->email }}" disabled>
                </x-form.field>
                <x-form.input name="phone" type="tel" :label="__('Phone number')" :value="$user->phone ? \App\Support\PhoneNumber::display($user->phone) : null" placeholder="078 123 4567" />
                <x-form.select name="locale" :label="__('Language')" :options="config('marketplace.locales')" :value="$user->locale" />
                <x-ui.button>{{ __('Save profile') }}</x-ui.button>
            </form>
        </x-ui.card>
        <x-ui.card :title="__('Change password')">
            <form method="post" action="{{ route('account.profile.password') }}" class="space-y-5">
                @csrf @method('put')
                @foreach (['current_password' => __('Current password'), 'password' => __('New password'), 'password_confirmation' => __('Confirm new password')] as $field => $label)
                    <div>
                        <label for="{{ $field }}" class="label">{{ $label }}</label>
                        <input type="password" id="{{ $field }}" name="{{ $field }}" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" @class(['input', 'input-error' => $errors->password->has($field)]) required>
                        @if ($errors->password->has($field))<p class="mt-1.5 text-xs font-medium text-danger-700">{{ $errors->password->first($field) }}</p>@endif
                    </div>
                @endforeach
                <x-ui.button>{{ __('Change password') }}</x-ui.button>
            </form>
        </x-ui.card>
    </div>
</x-layouts.dashboard>
