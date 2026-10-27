<x-layouts.dashboard :title="__('Sell your art')" area="account">
    <div class="grid max-w-5xl gap-8 lg:grid-cols-[1fr_340px]">
        <div>
            <x-ui.page-header :eyebrow="__('Open your shop')" :title="__('Sell your art on :app', ['app' => config('app.name')])" :description="__('Upload your designs, choose your markup and we print, deliver and pay you for every sale.')" />
            <form method="post" action="{{ route('account.become-artist.store') }}" class="card space-y-5 p-6">
                @csrf
                <x-form.input name="display_name" :label="__('Shop name')" :value="$user->name" required :hint="__('Shown publicly on your shop and products.')" />
                <x-form.checkbox name="accept_agreement" :label="__('I accept the artist agreement')" :hint="__('I will only upload original artwork I own or have permission to sell.')" />
                <p class="text-xs text-muted"><a href="{{ route('pages.show', 'artist-agreement') }}" class="link" target="_blank">{{ __('Read the artist agreement') }}</a></p>
                <x-ui.button variant="accent">{{ __('Open my shop') }}</x-ui.button>
            </form>
        </div>
        <aside class="card space-y-4 p-6 text-sm lg:mt-24">
            @foreach ([[__('Upload'), __('High-resolution PNG, ideally with a transparent background.')], [__('Review'), __('Our team approves your shop and each design before it goes live.')], [__('Earn'), __('Your markup is paid to you after delivery. Withdraw to mobile money.')]] as $index => [$heading, $text])
                <div class="flex gap-3">
                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-700">{{ $index + 1 }}</span>
                    <div><p class="font-semibold">{{ $heading }}</p><p class="text-muted">{{ $text }}</p></div>
                </div>
            @endforeach
        </aside>
    </div>
</x-layouts.dashboard>
