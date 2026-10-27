@php $editing = $address->exists; @endphp
<x-layouts.dashboard :title="$editing ? __('Edit address') : __('Add address')" area="account">
    <div class="mb-4 text-sm"><a href="{{ route('account.addresses.index') }}" class="text-muted hover:text-ink">← {{ __('Addresses') }}</a></div>
    <x-ui.page-header :title="$editing ? __('Edit address') : __('Add address')" />
    <form method="post" action="{{ $editing ? route('account.addresses.update', $address) : route('account.addresses.store') }}" class="card max-w-2xl space-y-5 p-6">
        @csrf
        @if ($editing) @method('put') @endif
        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.input name="label" :label="__('Label (optional)')" :value="$address->label" :placeholder="__('Home, Work…')" />
            <x-form.input name="recipient_name" :label="__('Recipient name')" :value="$address->recipient_name ?? auth()->user()->name" required />
            <x-form.input name="phone" type="tel" :label="__('Phone number')" :value="$address->phone ? \App\Support\PhoneNumber::display($address->phone) : null" placeholder="078 123 4567" required />
            <x-form.field name="district_id" :label="__('District')" required>
                <select name="district_id" id="district_id" required @class(['input', 'input-error' => $errors->has('district_id')])>
                    <option value="">{{ __('Choose a district') }}</option>
                    @foreach ($districts as $province => $list)
                        <optgroup label="{{ $province }}">
                            @foreach ($list as $district)
                                <option value="{{ $district->id }}" @selected((string) old('district_id', $address->district_id) === (string) $district->id)>{{ $district->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </x-form.field>
            <x-form.input name="sector" :label="__('Sector')" :value="$address->sector" />
            <x-form.input name="street_address" :label="__('Street address or house number')" :value="$address->street_address" required />
            <x-form.input name="landmark" :label="__('Landmark (optional)')" :value="$address->landmark" class="sm:col-span-2" />
        </div>
        <x-form.checkbox name="is_default" :label="__('Use as my default address')" :checked="$address->is_default" />
        <div class="flex gap-3">
            <x-ui.button>{{ $editing ? __('Save changes') : __('Save address') }}</x-ui.button>
            <x-ui.button :href="route('account.addresses.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
        </div>
    </form>
</x-layouts.dashboard>
