<x-pages.document :title="__('Artist agreement')" :eyebrow="__('For artists')" :updated="'26 October 2026 (version '.config('marketplace.agreement_version').')'">
    <p>This agreement applies when you open an artist shop on {{ config('app.name') }}.</p>
    <h2>1. Your rights and warranties</h2>
    <ul><li>You own, or are licensed to use, every element of the artwork you upload.</li><li>Your artwork does not include copyrighted characters, logos, trademarks, celebrity likenesses or third-party artwork without written permission.</li><li>Your artwork does not contain hate speech, sexual content involving minors, or content that is illegal in Rwanda.</li></ul>
    <h2>2. Licence to us</h2>
    <p>You grant {{ config('app.name') }} a non-exclusive licence to display your artwork, reproduce it on products ordered by customers, and use it to promote your shop and the marketplace. You keep ownership and can remove designs at any time; orders already placed will still be fulfilled.</p>
    <h2>3. Moderation</h2>
    <p>Every design is reviewed before publication. We may reject or remove designs that break this agreement and suspend shops after repeated infringement.</p>
    <h2>4. Earnings</h2>
    <ul><li>For each product you choose a markup within the limits shown in your studio. Your markup is your earning per unit sold.</li><li>Earnings are recorded when an order is paid, held until the item is delivered, and become available after the settlement hold period (currently {{ app(\App\Services\Settings::class)->integer('payouts.hold_days') }} days after delivery).</li><li>Refunds for damaged or failed items reduce your earnings for that item proportionally. Promotional discounts do not reduce your earnings.</li><li>You can request a payout to a verified MTN Mobile Money, Airtel Money or bank account once your available balance reaches {{ \App\Support\Money::format(app(\App\Services\Settings::class)->integer('payouts.minimum_amount'), 'RWF') }}.</li></ul>
    <h2>5. Taxes</h2>
    <p>You are responsible for declaring your income to the Rwanda Revenue Authority where required.</p>
</x-pages.document>
