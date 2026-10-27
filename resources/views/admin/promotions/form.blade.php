<x-layouts.dashboard :title="$promotion->exists ? __('Edit promotion') : __('New promotion')" area="admin">
    <x-ui.page-header :title="$promotion->exists ? $promotion->translate('title') : __('New promotion')" />
    <x-ui.card class="max-w-3xl">
        <form method="post" enctype="multipart/form-data" action="{{ $promotion->exists ? route('admin.promotions.update', $promotion) : route('admin.promotions.store') }}" class="grid gap-5 sm:grid-cols-2">
            @csrf @if ($promotion->exists) @method('put') @endif
            <x-form.input name="title_en" :label="__('Title (English)')" :value="$promotion->title['en'] ?? null" required />
            <x-form.input name="title_rw" :label="__('Title (Kinyarwanda)')" :value="$promotion->title['rw'] ?? null" />
            <x-form.input name="subtitle_en" :label="__('Subtitle (English)')" :value="$promotion->subtitle['en'] ?? null" />
            <x-form.input name="subtitle_rw" :label="__('Subtitle (Kinyarwanda)')" :value="$promotion->subtitle['rw'] ?? null" />
            <x-form.input name="cta_label_en" :label="__('Button label')" :value="$promotion->cta_label['en'] ?? null" />
            <x-form.input name="cta_url" :label="__('Button link (/path or https://)')" :value="$promotion->cta_url" />
            <x-form.select name="placement" :label="__('Placement')" :options="['home_banner' => __('Homepage banner'), 'home_hero' => __('Homepage hero')]" :value="$promotion->placement" />
            <x-form.select name="theme" :label="__('Colour theme')" :options="['ink' => __('Ink'), 'clay' => __('Clay'), 'leaf' => __('Leaf')]" :value="$promotion->theme" />
            <x-form.input name="starts_at" type="datetime-local" :label="__('Starts')" :value="$promotion->starts_at?->format('Y-m-d\TH:i')" />
            <x-form.input name="ends_at" type="datetime-local" :label="__('Ends')" :value="$promotion->ends_at?->format('Y-m-d\TH:i')" />
            <x-form.input name="image" type="file" :label="__('Image (optional)')" accept="image/*" />
            <x-form.input name="position" type="number" :label="__('Position')" :value="$promotion->position" />
            <x-form.checkbox name="is_active" :label="__('Active')" :checked="$promotion->is_active" class="sm:col-span-2" />
            <div class="sm:col-span-2"><x-ui.button>{{ __('Save') }}</x-ui.button></div>
        </form>
    </x-ui.card>
</x-layouts.dashboard>
