<x-pages.document :title="__('Sell your art on :app', ['app' => config('app.name')])" :eyebrow="__('For artists')" :draft="false">
    <p class="text-lg">Turn your artwork into T-shirts, hoodies, totes, mugs and stickers — without buying stock, printing or delivering anything yourself.</p>
    <h2>How it works</h2>
    <ol class="space-y-3">
        <li><strong>Open your shop.</strong> Create an account and accept the <a href="{{ route('pages.show', 'artist-agreement') }}">artist agreement</a>. Your shop lives at a link you can share anywhere.</li>
        <li><strong>Upload original artwork.</strong> PNG with a transparent background works best. For apparel, upload at least 3000 px wide for sharp prints.</li>
        <li><strong>Place it on products.</strong> Position and scale your design on each product. We check print resolution so customers get crisp results.</li>
        <li><strong>Set your markup.</strong> You choose how much you earn on each product within the platform limits.</li>
        <li><strong>Get approved and sell.</strong> Our team reviews each design. Once published, every sale earns you money.</li>
        <li><strong>Withdraw.</strong> Request payouts to MTN Mobile Money, Airtel Money or a bank account.</li>
    </ol>
    <div class="pt-4">
        <x-ui.button :href="auth()->check() ? route('account.become-artist') : route('register', ['as' => 'artist'])" variant="accent" size="lg">{{ __('Open your free shop') }}</x-ui.button>
    </div>
</x-pages.document>
