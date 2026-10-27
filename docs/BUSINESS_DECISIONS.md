# Open business decisions

Defaults were chosen so the platform works end to end. Each item below is configurable or
isolated in one service, and should be confirmed by the business before launch.

| # | Decision | Current default | Where to change |
|---|---|---|---|
| 1 | Who bears promotional discounts | Platform; artist earnings unchanged | `SaleRecorder`, `docs/PRICING.md` |
| 2 | Platform margin and artist markup limits | 25 % margin; artist 0–100 %, default 20 %; fixed markup up to RWF 20,000; prices rounded up to RWF 100 | Admin › Pricing rules |
| 3 | Settlement hold period | 7 days after delivery | Admin › Settings (`payouts.hold_days`) |
| 4 | Minimum payout | RWF 5,000 | Admin › Settings (`payouts.minimum_amount`) |
| 5 | VAT registration | Prices shown VAT-inclusive at 18 %; set to 0 if not registered | Admin › Countries |
| 6 | EBM / e-invoicing | Not integrated; orders carry the VAT breakdown needed for EBM | New integration required if registered |
| 7 | Licensing for collecting and disbursing funds | Assumes collection via licensed MoMo/aggregator accounts and manual payouts | Legal advice (BNR Regulation 74/2023) |
| 8 | Refund impact on artists | Proportional reversal for refunded share of a line; platform absorbs delivery refunds | `RefundService` |
| 9 | Cost of failed production | Admin decides per failed job whether the partner is paid | Admin order view |
| 10 | Cash on delivery | Disabled | Admin › Settings + country flag |
| 11 | Guest checkout | Allowed (e-mail + phone required); tracking via secret link | `CheckoutController` |
| 12 | Base costs and partner unit costs | Illustrative values | Admin › Products / Partners |
| 13 | Artist approval | Required before shop is public; each design moderated | Admin › Artists / Moderation |
| 14 | Return policy | Reprint or refund for damage/misprint/non-delivery; no change-of-mind returns | `resources/views/pages/refunds.blade.php` |
| 15 | Delivery zones and fees | Kigali RWF 2,000 (free over 60,000), provinces RWF 3,500 (free over 80,000) | Admin › Countries & delivery |
| 16 | Data hosting | Must be in Rwanda unless NCSA authorises otherwise | Infrastructure choice |

Legal pages (`/pages/terms`, `privacy`, `refunds`, `artist-agreement`) are templates and are
marked as such on screen until reviewed by a Rwandan legal adviser (remove the banner by
passing `:draft="false"` in the page view).
