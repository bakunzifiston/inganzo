<x-pages.document :title="__('Terms of service')" :eyebrow="__('Legal')">
    <p>These terms govern your use of {{ config('app.name') }}, an online marketplace operated from Kigali, Rwanda, where independent artists offer their designs on products that are printed on demand by our printing partners.</p>
    <h2>1. Who we are</h2>
    <p>{{ config('app.name') }} provides the marketplace, takes payment, coordinates production with printing partners and arranges delivery. Artists own the designs they upload and grant us a licence to reproduce them on products ordered through the platform.</p>
    <h2>2. Accounts</h2>
    <ul><li>You can browse and buy as a guest. An account lets you see your order history, save addresses and favourites.</li><li>You are responsible for keeping your password secret. Tell us immediately if you suspect misuse.</li><li>We may suspend accounts used for fraud, abuse or repeated infringement.</li></ul>
    <h2>3. Orders and prices</h2>
    <ul><li>Prices are shown in Rwandan francs (RWF) and include VAT where applicable. Delivery fees are shown at checkout.</li><li>Your order is confirmed when payment is verified with the payment provider. Production starts only after confirmation (or, for cash on delivery, after we accept the order).</li><li>Because products are made to order, colours may vary slightly from on-screen previews.</li></ul>
    <h2>4. Payment</h2>
    <p>We accept the payment methods listed at checkout, such as MTN Mobile Money and Airtel Money. We never store your mobile-money PIN or card details.</p>
    <h2>5. Delivery</h2>
    <p>Estimated production and delivery times are shown on product pages and at checkout. They are estimates, not guarantees. See our <a href="{{ route('pages.show', 'delivery') }}">delivery information</a>.</p>
    <h2>6. Returns and refunds</h2>
    <p>See our <a href="{{ route('pages.show', 'refunds') }}">refund policy</a>.</p>
    <h2>7. Content and intellectual property</h2>
    <p>Artists confirm they hold the rights to everything they upload. We review designs before publication and remove infringing content. Report concerns through our <a href="{{ route('copyright.create') }}">infringement form</a>.</p>
    <h2>8. Liability</h2>
    <p>Nothing in these terms limits rights you have under Rwandan consumer protection law. Subject to that, our liability for an order is limited to the amount you paid for it.</p>
    <h2>9. Governing law</h2>
    <p>These terms are governed by the laws of the Republic of Rwanda.</p>
</x-pages.document>
