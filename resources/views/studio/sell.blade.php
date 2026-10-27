<x-layouts.dashboard :title="__('Add product')" area="studio">
    <x-ui.page-header :title="__('What would you like to sell?')" :description="__('One artist account, two ways to sell. Use either, or both.')" />

    <div class="grid max-w-5xl gap-6 md:grid-cols-2">
        <section class="card flex flex-col p-6 sm:p-8">
            <span class="grid size-12 place-items-center rounded-2xl bg-info-50 text-info-700"><x-ui.icon name="palette" class="size-6" /></span>
            <h2 class="mt-5 text-xl font-bold">{{ __('Sell a design') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('Upload your artwork and we print it on T-shirts, hoodies, mugs and more — only when someone orders.') }}</p>
            <ul class="mt-5 space-y-2 text-sm">
                @foreach ([__('No stock to hold or ship'), __('Print size and resolution are checked for you'), __('Printed and delivered by our partners'), __('You earn a markup on every sale')] as $point)
                    <li class="flex gap-2"><x-ui.icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-success-600" /> {{ $point }}</li>
                @endforeach
            </ul>
            <div class="mt-8 flex flex-wrap gap-2 pt-2 sm:mt-auto">
                <x-ui.button :href="route('studio.artworks.create')" variant="accent"><x-ui.icon name="upload" class="size-4" /> {{ __('Upload a design') }}</x-ui.button>
                <x-ui.button :href="route('studio.design.create')" variant="secondary"><x-ui.icon name="pencil" class="size-4" /> {{ __('Design Studio') }}</x-ui.button>
            </div>
        </section>

        <section class="card flex flex-col p-6 sm:p-8">
            <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-700"><x-ui.icon name="package" class="size-6" /></span>
            <h2 class="mt-5 text-xl font-bold">{{ __('Sell a finished product') }}</h2>
            <p class="mt-1 text-sm text-muted">{{ __('List a complete physical product — handmade, manufactured or already in stock. No artwork or print check needed.') }}</p>
            <ul class="mt-5 space-y-2 text-sm">
                @foreach ([__('You set the price, sizes, colours and stock'), __('Customers can only buy what is available'), __('You pack and ship each order yourself'), __('Photos, SKU and shipping details included')] as $point)
                    <li class="flex gap-2"><x-ui.icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-success-600" /> {{ $point }}</li>
                @endforeach
            </ul>
            <div class="mt-8 pt-2 sm:mt-auto">
                <x-ui.button :href="route('studio.finished.create')" variant="accent"><x-ui.icon name="plus" class="size-4" /> {{ __('Add a finished product') }}</x-ui.button>
            </div>
        </section>
    </div>
</x-layouts.dashboard>
