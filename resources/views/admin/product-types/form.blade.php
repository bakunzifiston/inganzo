<x-layouts.dashboard :title="$type->exists ? __('Edit product type') : __('New product type')" area="admin">
    <x-ui.page-header :title="$type->exists ? $type->translate('name') : __('New product type')" />
    <x-ui.card class="max-w-2xl">
        <form method="post" action="{{ $type->exists ? route('admin.product-types.update', $type) : route('admin.product-types.store') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf @if ($type->exists) @method('put') @endif
            <x-form.input name="name_en" :label="__('Name (English)')" :value="$type->name['en'] ?? null" required />
            <x-form.input name="name_rw" :label="__('Name (Kinyarwanda)')" :value="$type->name['rw'] ?? null" />
            <x-form.input name="slug" :label="__('Slug')" :value="$type->slug" required />
            <x-form.input name="position" type="number" :label="__('Position')" :value="$type->position" />
            <x-form.textarea name="description_en" :label="__('Description')" :value="$type->description['en'] ?? null" rows="2" class="sm:col-span-2" />
            <x-form.checkbox name="is_active" :label="__('Active')" :checked="$type->is_active" />
            <div class="sm:col-span-2"><x-ui.button>{{ __('Save') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
