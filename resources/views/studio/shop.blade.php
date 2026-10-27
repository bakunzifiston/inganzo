<x-layouts.dashboard :title="__('Shop settings')" area="studio">
    <x-ui.page-header :title="__('Shop settings')" :description="__('This is how customers see you.')">
        <x-slot:actions><x-ui.button :href="route('artists.show', $artist)" variant="secondary" size="sm">{{ __('Preview shop') }}</x-ui.button></x-slot:actions>
    </x-ui.page-header>
    <form method="post" action="{{ route('studio.shop.update') }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[1fr_340px]">
        @csrf @method('put')
        <x-ui.card :title="__('Profile')">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="display_name" :label="__('Shop name')" :value="$artist->display_name" required />
                <x-form.field name="slug" :label="__('Shop address')" :hint="__('Lowercase letters, numbers and dashes.')">
                    <div class="flex items-center rounded-lg border border-line-strong bg-sand text-sm">
                        <span class="pl-3 text-muted">/artists/</span>
                        <input name="slug" id="slug" value="{{ old('slug', $artist->slug) }}" class="w-full rounded-r-lg border-0 bg-white px-2 py-2.5 focus:ring-0" required>
                    </div>
                </x-form.field>
                <x-form.input name="tagline" :label="__('Tagline')" :value="$artist->tagline" class="sm:col-span-2" maxlength="160" />
                <x-form.textarea name="bio" :label="__('Biography')" :value="$artist->bio" rows="5" class="sm:col-span-2" />
                <x-form.input name="location" :label="__('Location')" :value="$artist->location" placeholder="Kigali" />
                <x-form.input name="website_url" type="url" :label="__('Website')" :value="$artist->website_url" />
                <x-form.input name="instagram_handle" :label="__('Instagram')" :value="$artist->instagram_handle" placeholder="@yourname" />
                <x-form.input name="x_handle" :label="__('X (Twitter)')" :value="$artist->x_handle" placeholder="@yourname" />
            </div>
        </x-ui.card>
        <div class="space-y-6">
            <x-ui.card :title="__('Images')">
                <div class="flex items-center gap-4">
                    <x-shop.avatar :artist="$artist" class="size-16" />
                    <x-form.input name="avatar" type="file" :label="__('Profile picture')" accept="image/png,image/jpeg,image/webp" :hint="__('Square, at least 200 px.')" />
                </div>
                <div class="mt-5">
                    @if ($artist->bannerUrl())<img src="{{ $artist->bannerUrl() }}" alt="" class="mb-3 h-20 w-full rounded-lg object-cover">@endif
                    <x-form.input name="banner" type="file" :label="__('Banner')" accept="image/png,image/jpeg,image/webp" :hint="__('At least 1200 × 300 px.')" />
                </div>
            </x-ui.card>
            <x-ui.card>
                <p class="text-sm text-muted">{{ __('Account status') }}: <x-ui.badge :status="$artist->status" /></p>
                <x-ui.button variant="accent" class="mt-4 w-full">{{ __('Save changes') }}</x-ui.button>
            </x-ui.card>
        </div>
    </form>
</x-layouts.dashboard>
