<x-layouts.dashboard :title="$category->exists ? __('Edit category') : __('New category')" area="admin">
    <x-ui.page-header :title="$category->exists ? $category->translate('name') : __('New category')" />
    <x-ui.card class="max-w-3xl">
        <form method="post" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf @if ($category->exists) @method('put') @endif
            <x-form.input name="name_en" :label="__('Name (English)')" :value="$category->name['en'] ?? null" required />
            <x-form.input name="name_rw" :label="__('Name (Kinyarwanda)')" :value="$category->name['rw'] ?? null" />
            <x-form.input name="slug" :label="__('Slug')" :value="$category->slug" required />
            <x-form.select name="parent_id" :label="__('Parent')" :options="$parents" :value="$category->parent_id" :placeholder="__('None (top level)')" />
            <x-form.textarea name="description_en" :label="__('Description (English)')" :value="$category->description['en'] ?? null" rows="2" />
            <x-form.textarea name="description_rw" :label="__('Description (Kinyarwanda)')" :value="$category->description['rw'] ?? null" rows="2" />
            <x-form.input name="position" type="number" :label="__('Position')" :value="$category->position" />
            <div class="flex items-end gap-6"><x-form.checkbox name="is_active" :label="__('Active')" :checked="$category->is_active" /><x-form.checkbox name="is_featured" :label="__('Featured')" :checked="$category->is_featured" /></div>
            <div class="sm:col-span-2"><x-ui.button>{{ __('Save') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
