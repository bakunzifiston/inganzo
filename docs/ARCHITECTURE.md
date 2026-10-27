# Architecture

Laravel 13 · PHP 8.4 · PostgreSQL 17 · Blade + Livewire 4 + Alpine · Tailwind CSS 4 · Chart.js.

## Layers

| Layer | Location | Notes |
|---|---|---|
| HTTP | `app/Http/Controllers/{Shop,Account,Studio,Partner,Admin,Auth,Api/V1}` | Thin controllers; validation in form requests; authorization via policies/gates |
| Domain services | `app/Services/*` | All business rules: pricing, artwork, cart/checkout, payments, fulfillment, finance, reporting |
| Models | `app/Models` | Relations, casts, scopes. Status columns are PHP backed enums (`app/Enums`) |
| Jobs / commands | `app/Jobs`, `app/Console/Commands`, `routes/console.php` | Image processing, partner assignment, payment reconciliation, earnings release |
| Views | `resources/views` | Design-system components in `components/{ui,form,shop,layouts}` |
| Front-end | `resources/js` | Alpine components: product editor, payment poller, upload progress, charts |

Routes are split per area (`routes/web.php`, `account.php`, `studio.php`, `partner.php`,
`admin.php`, `api.php`) and wired in `bootstrap/app.php` with their middleware.

## Roles and authorization

Roles (`roles`, `role_user`) and permissions (`permissions`, `permission_role`) are database
records seeded from `App\Enums\RoleName` and `App\Enums\Ability`. Every `Ability` is a Gate
(`AppServiceProvider::registerAbilities`); super administrators pass every ability.

| Role | Access |
|---|---|
| super_admin | everything, including staff permissions, finance rules and settings |
| admin | operations, catalogue, content, users (not staff roles/finance rules/settings) |
| finance | orders (read), payments, refunds, ledger, pricing rules, payouts, audit log |
| moderator | artists, artwork moderation, copyright reports |
| support | orders (read), users (read), support tickets |
| artist | `/studio` – only their own artwork, sales and earnings (`ArtworkPolicy`, scoped queries) |
| partner_staff | `/partner` – only jobs and shipments of their facility (`ProductionJobPolicy`, `ShipmentPolicy`) |
| customer | `/account` – only their own orders (`OrderPolicy`) |

Artwork files are on a private disk and served by `MediaController` after
`ArtworkVersionPolicy` checks: previews follow artwork visibility; full-resolution originals
are available to the artist, moderators/order managers and partners who hold a job for that
exact version.

## Key workflows

1. **Upload** – `ArtworkUploader` validates by content (finfo + getimagesize), dimensions and
   size, stores the original on the private disk and queues `ProcessArtworkVersion`
   (WebP preview + thumbnail, transparency detection). Uploading a new version unpublishes
   approved artwork and re-validates all placements.
2. **Product configuration** – `ArtistProductConfigurator` + `PlacementValidator` keep the
   design inside the print area and compute effective DPI (fail < `min_dpi`, warn <
   `recommended_dpi`). Placement is stored per print area as fractions (offset, scale,
   rotation), independent from the source artwork.
3. **Moderation** – `ArtworkModeration`: draft → pending_review → approved/rejected;
   suspended (takedown) and reinstated. Only approved artwork from approved artists is listed.
4. **Checkout** – `CartManager` + `CheckoutService` recalculate prices, delivery, discounts and
   VAT inside a transaction that locks the cart and the discount code. Each order item freezes
   the price split and the print specification (`placement_snapshot`).
5. **Payment** – see `docs/PAYMENTS.md`.
6. **Fulfillment** – `AssignOrderToPartners` → `PartnerAssigner` (capability, service area,
   load/capacity, priority) → `ProductionWorkflow` (accept, print, QC pass/fail, issue, fail,
   reprint) → `ShipmentService` (partial shipments, tracking events, delivery, COD cash
   confirmation) → `OrderProgress` derives order fulfilment status and completion.
7. **Finance** – `SaleRecorder`, `EarningsReleaser`, `RefundService`, `PayoutService`,
   `LedgerAdjuster` write to the immutable ledger. See `docs/PRICING.md`.

Statuses are deliberately separate: `orders.status` (commercial), `orders.payment_status`,
`orders.fulfillment_status`, `order_items.fulfillment_status`, `payments.status`,
`production_jobs.status`, `shipments.status`, `payout_requests.status`, `refunds.status`.

## Database

See the migrations in `database/migrations` (grouped by domain, each with a full `down()`).
PostgreSQL features in use: CHECK constraints for money invariants, partial unique indexes
(one successful payment per order, one active production job per item, one open payout per
artist, one cart per user), `NULLS NOT DISTINCT` unique constraints, a generated `tsvector`
column with a GIN index for search, JSONB for genuinely flexible data (translations,
placement snapshots, provider payloads, audit changes) and append-only triggers on
`ledger_entries` and `audit_logs`.

Tables follow the requested list with these deliberate simplifications:

- `artist_shops` is merged into `artist_profiles` (one shop per artist); payout details live
  in `payout_accounts` (encrypted account numbers).
- `payment_attempts` is represented by `payments` (one row per attempt) plus
  `payment_events` (requests, callbacks, status checks).
- `commission_rules` are part of `pricing_rules` (platform margin + artist markup limits).
- `artist_product_configurations` is `artist_product_placements` (per print area) plus
  `artist_product_colors`.
- `user_roles` is Laravel's conventional `role_user` pivot.
- Added: `districts`, `delivery_zones`, `exchange_rates`, `partner_members`,
  `partner_service_areas`, `shipment_items`, `support_tickets`, `promotions`.

## Localization

English and Kinyarwanda. Interface text uses `__()` with English source strings and
`lang/rw.json` for Kinyarwanda; validation and framework messages fall back to English.
Catalogue content (categories, product names, collections, promotions) is stored as JSONB
`{ "en": …, "rw": … }` and read with `HasTranslations::translate()`. The locale comes from
the user's preference, the session (footer switcher) or the browser. Adding Kiswahili means
adding `sw` to `config/marketplace.php` and `lang/sw.json`.

Currency is explicit on every money row. Orders keep their original currency and amounts;
`exchange_rates` are for display/reporting only and never rewrite history.

## Colour theme

All colours are tokens in the `@theme` blocks of `resources/css/app.css`; change the palette
there only. Families are named by purpose:

| Token family | Use | Base value |
|---|---|---|
| `brand-*` | primary buttons and active states (dark text on gold); links, icons and focus rings use the darker `brand-700` `#A16207` | `#F5B027` (`brand-600`) |
| `ink` / `ink-soft` | sidebar, headings, strong text | `#171923` |
| `paper` | dashboard background | `#F7F8FC` |
| `surface` / `white` | cards, tables, forms, modals | `#FFFFFF` |
| `muted` / `subtle` | secondary and tertiary text | `#64748B` / `#94A3B8` |
| `line` / `line-strong` | borders, dividers, input borders | `#E5E7EB` / `#D1D5DB` |
| `success-*` | published, print ready, paid, delivered | `#16A34A` |
| `warning-*` | pending review, low resolution, waiting | `#D97706` |
| `danger-*` | errors, rejected, not printable, failed | `#DC2626` |
| `info-*` | informational and processing states | `#2563EB` |

Status badges map enum tones (`App\Enums\HasBadge::tone()`) to these families in
`components/ui/badge.blade.php`. Charts (`resources/js/charts.js`) read the same CSS variables
at runtime; Blade passes token names such as `--color-success-600` for chart colours. The
only literal colours left outside the theme are product garment colours (catalogue data),
illustrative category icons, the MTN/Airtel brand chips and the favicon data URI.
