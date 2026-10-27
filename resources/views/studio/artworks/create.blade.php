<x-layouts.dashboard :title="__('Upload design')" area="studio">
    <x-ui.page-header :title="__('Upload a new design')" :description="__('PNG, JPEG or WebP. Transparent PNGs work best on apparel.')" />

    <form method="post" action="{{ route('studio.artworks.store') }}" enctype="multipart/form-data"
        x-data="artworkUpload({{ config('marketplace.artwork.min_pixels') }}, {{ config('marketplace.artwork.max_upload_kb') }})"
        @submit="submit($event)"
        data-type-error="{{ __('Upload a PNG, JPEG or WebP image.') }}"
        data-size-error="{{ __('The file is larger than :mb MB.', ['mb' => intdiv(config('marketplace.artwork.max_upload_kb'), 1024)]) }}"
        data-dimension-error="{{ __('Artwork must be at least :min pixels on each side.', ['min' => config('marketplace.artwork.min_pixels')]) }}"
        data-network-error="{{ __('The upload failed. Check your connection and try again.') }}"
        class="grid gap-8 lg:grid-cols-[1fr_380px]">
        @csrf
        <div class="card p-6">
            <label for="file" class="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-line-strong bg-checker p-6 text-center transition hover:border-brand-600">
                <template x-if="preview"><img :src="preview" alt="" class="max-h-full max-w-full object-contain"></template>
                <template x-if="! preview">
                    <span class="flex flex-col items-center">
                        <span class="grid size-14 place-items-center rounded-full bg-white text-brand-700 shadow-sm"><x-ui.icon name="upload" class="size-6" /></span>
                        <span class="mt-4 font-semibold">{{ __('Choose a file or drag it here') }}</span>
                        <span class="mt-1 text-sm text-muted">{{ __('Minimum :min px · up to :mb MB', ['min' => config('marketplace.artwork.min_pixels'), 'mb' => intdiv(config('marketplace.artwork.max_upload_kb'), 1024)]) }}</span>
                    </span>
                </template>
            </label>
            <input id="file" name="file" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pick($event)" required>
            <div class="mt-3 flex items-center justify-between text-sm">
                <span class="truncate text-muted" x-text="name"></span>
                <span class="text-muted" x-text="info"></span>
            </div>
            <p x-show="error" x-text="error" class="mt-2 text-sm font-medium text-danger-700"></p>
            @error('file')<p class="mt-2 text-sm font-medium text-danger-700">{{ $message }}</p>@enderror
            <p class="mt-4 rounded-lg bg-sand px-3 py-2 text-xs text-muted">{{ __('Print quality depends on pixels per printed inch (DPI). A 3000 px wide file prints a sharp 30 cm design at about 250 DPI; a 1250 px file only prints sharply up to about 12.7 cm. Upload your original export — enlarging a small image, even with AI upscaling, does not add the real detail of your artwork.') }}</p>
            <div x-show="uploading" class="mt-4">
                <div class="h-2 overflow-hidden rounded-full bg-sand"><div class="h-full bg-brand-600 transition-all" :style="`width: ${progress}%`"></div></div>
                <p class="mt-1 text-xs text-muted" x-text="progress < 100 ? `{{ __('Uploading') }} ${progress}%` : '{{ __('Processing your artwork…') }}'"></p>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card space-y-5 p-6">
                <x-form.input name="title" :label="__('Title')" required maxlength="120" />
                <x-form.textarea name="description" :label="__('Description')" rows="3" :hint="__('Tell customers the story behind the design.')" />
                <x-form.select name="category_id" :label="__('Category')" :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->translate('name')])" :placeholder="__('Choose a category')" />
                <x-form.input name="tags" :label="__('Tags')" :hint="__('Comma separated, up to 15. Helps customers find your work.')" placeholder="imigongo, pattern, kigali" />
                <x-form.checkbox name="is_mature" :label="__('Contains mature content')" />
            </div>
            <div class="card p-6">
                <x-form.checkbox name="rights_confirmed" :label="__('I own the rights to this artwork')" :hint="__('It is my original work or I have written permission to sell it. It contains no third-party characters, logos or trademarks.')" />
                <x-ui.button variant="accent" size="lg" class="mt-5 w-full" x-bind:disabled="uploading">{{ __('Upload design') }}</x-ui.button>
                <p class="mt-3 text-center text-xs text-muted"><a href="{{ route('pages.show', 'artist-agreement') }}" class="underline" target="_blank">{{ __('Artist agreement') }}</a></p>
            </div>
        </div>
    </form>
</x-layouts.dashboard>
