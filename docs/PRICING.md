# Pricing, commissions and the financial ledger

All money is stored as **integer minor units** (`*_amount` columns, `bigint`) with an explicit
ISO currency. RWF has 0 decimals, so `9500` means RWF 9,500. Percentages are **basis points**
(1 bps = 0.01 %). Floating point is never used for money (`App\Support\Money`).

## Customer price formula

Implemented in `App\Services\Pricing\PriceCalculator`.

```
base cost        = products.base_cost_amount + product_variants.extra_cost_amount
artist earning   = base cost × artist markup bps / 10 000        (percentage markup)
                 | artist_products.markup_amount                   (fixed markup, if the rule allows)
platform margin  = base cost × pricing_rules.platform_margin_bps / 10 000 + platform_fixed_amount
raw price        = base cost + artist earning + platform margin
customer price   = raw price rounded UP to pricing_rules.rounding_increment
                   (the rounding difference is added to the platform margin)
```

Invariant (enforced by a CHECK constraint on `order_items`):
`unit_price = unit_base_cost + unit_artist_earning + unit_platform_margin`.

Example (seeded default rule: 25 % platform margin, 20 % default artist markup, 100 RWF rounding):

| | RWF |
|---|---|
| Classic T-shirt base cost | 6,500 |
| Artist earning (20 %) | 1,300 |
| Platform margin (25 % + 75 rounding) | 1,700 |
| **Customer price** | **9,500** |

### Pricing rules

`pricing_rules` rows hold the platform margin and the limits artists may choose within
(`artist_markup_min_bps`, `artist_markup_max_bps`, `artist_markup_default_bps`,
`artist_fixed_max_amount` – null disables fixed markups). The most specific **active** rule
in the product's currency wins: product › product type › global. Rules can be time-boxed
(`starts_at`, `ends_at`). Changing a rule affects future prices only; existing orders keep
their frozen breakdown. Only users with `finance.manage` may edit rules and every change is
written to `audit_logs`. Run `php artisan prices:refresh` after changes to update the cached
"from" prices used for listing and filtering (checkout always recalculates).

### VAT

Prices are **VAT inclusive**. `countries.tax_rate_bps` (Rwanda seeded at 1800 = 18 %)
determines the VAT portion recorded on each order: `tax = total × rate / (10 000 + rate)`.
Set the rate to 0 until the business is VAT-registered (registration threshold: RWF 20 M
turnover in 12 months or RWF 5 M in a quarter – confirm with RRA).

### Discounts

Discount codes reduce the order subtotal and are allocated across lines
(largest-remainder method) for reporting. **Discounts are borne by the platform; artist
earnings are not reduced.** This is a business decision – see `docs/BUSINESS_DECISIONS.md`.

### Delivery

Delivery fees come from `delivery_zones` (by district) with an optional free-delivery
threshold. Delivery income is platform revenue.

## Ledger

`ledger_entries` is append-only (PostgreSQL trigger `prevent_mutation` rejects UPDATE and
DELETE; the Eloquent model also refuses). Every entry has a deterministic `entry_key`, and
inserts use `ON CONFLICT DO NOTHING`, so replaying an event never double-posts.

| Event | Entries |
|---|---|
| Payment verified (`SaleRecorder`) | artist/held `+earning` per item · platform/revenue `+margin`, `+base cost collected` per item · platform `+delivery` · platform `−discount` · platform `−fee` and processor `+fee` when known |
| Job dispatched (or failed with cost) | partner/payable `+unit cost × qty` · platform/revenue `−same` |
| Earnings released (`earnings:release`, daily) | artist/held `−x` · artist/available `+x` |
| Refund processed (`RefundService`) | artist reversal (held first, then available) proportional to the refunded share of the line · platform absorbs the remainder |
| Payout requested | artist/available `−amount` |
| Payout rejected / failed | artist/available `+amount` |
| Manual correction | `adjustment` entries (artist and platform), audited |

### Settlement policy

An item's artist earning becomes **available** once the item is delivered and
`payouts.hold_days` (default 7) have passed. Payout eligibility: approved artist, verified
payout account, available balance ≥ `payouts.minimum_amount` (default RWF 5,000), no other
payout in progress. Requests lock the artist row so the same balance cannot be requested twice.

### What counts as a sale

Reports use `Order::scopeCountableSales()` – orders with payment status `paid` or
`partially_refunded`. Pending, failed, cancelled and fully refunded orders are excluded.
