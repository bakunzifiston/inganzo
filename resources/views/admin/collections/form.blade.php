<x-layouts.dashboard :title="$collection->exists ? __('Edit collection') : __('New collection')" area="admin">
    <x-ui.page-header :title="$collection->exists ? $collection->translate('title') : __('New collection')" />
    <x-ui.card class="max-w-3xl">
        <form method="post" action="{{ $collection->exists ? route('admin.collections.update', $collection) : route('admin.collections.store') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf @if ($collection->exists) @method('put') @endif
            <x-form.input name="title_en" :label="__('Title (English)')" :value="$collection->title['en'] ?? null" required />
            <x-form.input name="title_rw" :label="__('Title (Kinyarwanda)')" :value="$collection->title['rw'] ?? null" />
            <x-form.input name="slug" :label="__('Slug')" :value="$collection->slug" required />
            <x-form.input name="position" type="number" :label="__('Position')" :value="$collection->position" />
            <x-form.textarea name="description_en" :label="__('Description')" :value="$collection->description['en'] ?? null" rows="2" class="sm:col-span-2" />
            <x-form.textarea name="artwork_slugs" :label="__('Designs (one slug or product link per line, in display order)')" :value="$artworkSlugs" rows="8" class="sm:col-span-2 font-mono" />
            <div class="flex gap-6 sm:col-span-2"><x-form.checkbox name="is_active" :label="__('Active')" :checked="$collection->is_active" /><x-form.checkbox name="is_featured" :label="__('Feature on homepage')" :checked="$collection->is_featured" /></div>
            <div class="sm:col-span-2"><x-ui.button>{{ __('Save') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
