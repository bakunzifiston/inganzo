# Inganzo — print-on-demand marketplace for Rwanda and East Africa

Independent artists upload original artwork, place it on T-shirts, hoodies, totes, mugs and
stickers, and earn on every sale. Customers browse, personalise colour and size, and pay with
Mobile Money. Local printing partners produce each item on demand and deliver it.

*Inganzo* is Kinyarwanda for the source of creative inspiration.

## What is included

- **Storefront** – home, live-filtered catalogue (Livewire, PostgreSQL full-text search),
  product pages with colour-accurate mockups, artist shops, collections, cart, guest or
  account checkout, order tracking by secret link, support and copyright reporting, legal pages.
- **Artist studio** – secure upload with content validation and background processing,
  interactive mockup editor (drag/scale/rotate inside the print area, live DPI check,
  colours, markup and live price), submission for moderation, sales, ledger, payout requests,
  shop customisation.
- **Design Studio** – canvas editor (Fabric.js) for composing artwork from text, shapes and
  photos on a print-area artboard at real size: layers, undo/redo, snapping, alignment,
  non-destructive crop, live per-image DPI checks, autosave with conflict detection, and
  export to a full-resolution transparent print file. See [docs/DESIGN_STUDIO.md](docs/DESIGN_STUDIO.md).
- **Payments** – provider-agnostic `PaymentGateway`; MTN MoMo and Airtel Money adapters,
  cash on delivery, and a clearly labelled development simulator. Verified callbacks only,
  idempotent and race-safe.
- **Operations** – automatic routing to printing partners, partner portal (production,
  quality control, shipments, tracking), partial fulfilment, reprints.
- **Finance** – server-side pricing engine, immutable ledger, settlement hold, refunds with
  proportional earning reversal, payouts.
- **Administration** – custom console with KPI dashboard and charts, orders, moderation,
  artists, catalogue, partners, pricing rules, payouts, refunds, discounts, content,
  localisation, users and roles, settings, audit log and system health.
- **API** – versioned REST endpoints under `/api/v1` (catalogue, artists, Sanctum-protected
  account orders, payment callbacks).
- English and Kinyarwanda; RWF by default with multi-country/currency foundations.

## Quick start

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # configure PostgreSQL in .env
php artisan migrate:fresh --seed                   # reference + demo data
npm run build && php artisan serve
```

Open http://localhost:8000 and sign in with a demo account (password `password`):
`admin@inganzo.test`, `artist1@inganzo.test`, `partner@inganzo.test`, `customer@inganzo.test`.
All demo content is generated and labelled "(demo)".

Run the tests (PostgreSQL database `inganzo_test`): `php artisan test`.

## Documentation

- [Architecture, roles and workflows](docs/ARCHITECTURE.md)
- [Pricing formula, ledger and settlement](docs/PRICING.md)
- [Payments and provider onboarding](docs/PAYMENTS.md)
- [Design Studio](docs/DESIGN_STUDIO.md)
- [Printing partners](docs/PRINTING_PARTNERS.md)
- [Deployment and operations](docs/DEPLOYMENT.md)
- [Open business decisions](docs/BUSINESS_DECISIONS.md)
# inganzo
