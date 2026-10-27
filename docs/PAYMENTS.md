# Payments

> Research snapshot: 26 October 2026. Items marked **(verify)** could not be confirmed from
> official documentation and must be confirmed with the provider during onboarding.

## Architecture

Every provider implements `App\Services\Payments\PaymentGateway` and is registered in
`GatewayRegistry`. Checkout, retry, webhooks and reconciliation are provider-agnostic
(`PaymentService`).

Safety rules implemented in `PaymentService`:

1. Totals are recalculated server-side (`CheckoutService`) and `payments.amount` is copied
   from the order; nothing from the browser is trusted.
2. **An order is never marked paid from a browser redirect.** The tracking page polls
   `/track/{token}/payment-status`, which asks the provider server-to-server.
3. Callbacks are logged in `payment_events`; `dedupe_key` is unique, so repeated deliveries
   are ignored.
4. Unsigned callbacks (MTN) are only a trigger: the status is re-fetched from the provider API.
5. The confirmed amount and currency must equal what was requested, otherwise the payment
   is failed with `amount mismatch` and logged at `critical`.
6. Payment and order rows are locked (`SELECT … FOR UPDATE`) while a status is applied, and
   a partial unique index allows only one `succeeded` payment per order. A second successful
   payment is stored as `duplicate` (refund due) instead of being applied twice.
7. `payments:reconcile` (every 5 minutes) checks pending payments and expires stale ones,
   because MTN sends a callback only once and without retries.
8. No card numbers or PINs are ever stored. Mobile-money numbers are stored for the payment
   record only.

Payment statuses: `pending → succeeded | failed | cancelled | expired | duplicate`.
Order payment status is separate: `unpaid, pending, paid, partially_refunded, refunded, failed`.

## Development simulator

`provider = simulator` (`PAYMENT_SIMULATOR_ENABLED=true`). Checkout redirects to a page
clearly labelled **"Development simulator — no real money moves"** where you approve or
decline. The simulator signs a callback with `PAYMENT_SIMULATOR_SECRET` (HMAC-SHA256) and
feeds it through the same `handleCallback` pipeline. It is **hard-disabled when
`APP_ENV=production`**, regardless of configuration.

## MTN Mobile Money (Rwanda) – `MtnMomoGateway`

Status: interface, request signing, callback handling and status verification implemented
and tested with HTTP fakes. **No live credentials are configured.**

- Product: MoMo Open API **Collections** (RequestToPay). Go-live portal:
  https://momodeveloper.mtn.co.rw/go-live · support: mmitsupport.RW@mtn.com
- Flow: `POST /collection/token/` (Basic api_user:api_key + `Ocp-Apim-Subscription-Key`) →
  Bearer token (1 h, cached 50 min) → `POST /collection/v1_0/requesttopay` with
  `X-Reference-Id` = our payment idempotency key (UUID v4), `X-Target-Environment`,
  `X-Callback-Url` → **202 Accepted** → customer approves with PIN →
  `GET /collection/v1_0/requesttopay/{X-Reference-Id}` returns `PENDING | SUCCESSFUL | FAILED`.
  A 409 (reference already used) is treated as pending, which makes retries idempotent.
- Callbacks are **not signed** and are sent **once**, as PUT or POST, only to the
  `providerCallbackHost` registered for the API user (HTTPS in production). We embed our
  reference in the callback URL and always re-query the status.
- Sandbox uses currency **EUR**: set `MTN_MOMO_SANDBOX_CURRENCY=EUR` in sandbox only.
- Target environment for Rwanda is expected to be `mtnrwanda` **(verify)**.
- **Refunds and artist payouts require the separate Disbursements product**
  (`/disbursement/v1_0/refund`, `/disbursement/v1_0/transfer`) **(verify)** and IP whitelisting.
  Until enabled, refunds and payouts are executed manually by finance staff and recorded in
  the admin console with the transfer reference.

Environment: `MTN_MOMO_ENABLED, MTN_MOMO_BASE_URL, MTN_MOMO_TARGET_ENVIRONMENT,
MTN_MOMO_COLLECTION_SUBSCRIPTION_KEY, MTN_MOMO_API_USER, MTN_MOMO_API_KEY,
MTN_MOMO_CALLBACK_HOST`.

Go-live checklist: merchant contract with MTN Rwanda · production subscription key ·
API user/key created for the production callback host · HTTPS callback URL
`https://<host>/api/v1/payments/callback/mtn_momo` reachable from MTN · test a 100 RWF
payment end-to-end · confirm settlement account and settlement schedule · confirm fees
(record them in `payments.fee_amount` for accurate net revenue).

## Airtel Money (Rwanda) – `AirtelMoneyGateway`

Status: implemented against the Airtel Africa Open API and tested with HTTP fakes.
**No credentials configured; Rwanda availability on the Open API must be confirmed (verify).**

- Hosts: UAT `https://openapiuat.airtel.africa`, production `https://openapi.airtel.africa`.
- `POST /auth/oauth2/token` (client credentials) → `POST /merchant/v1/payments/` with headers
  `X-Country: RW`, `X-Currency: RWF` and body `{reference, subscriber{country,currency,msisdn
  (without country code)}, transaction{amount,country,currency,id}}` →
  `GET /standard/v1/payments/{id}`: `TS` success, `TF` failed, `TA` ambiguous/pending,
  `TE` expired **(verify)**.
- Callback body `{transaction{id,message,status_code,airtel_money_id},hash}`. When callback
  authentication is enabled in the Airtel portal, set `AIRTEL_MONEY_CALLBACK_SECRET`; the
  HMAC-SHA256 is verified and invalid signatures are rejected with 401. Status is still
  confirmed by enquiry.
- Refund endpoint exists (partial/full by `airtel_money_id`) – path to be confirmed **(verify)**.

## Cash on delivery

Disabled by default. Enable with the `checkout.cod_enabled` setting **and** the country's
`cod_enabled` flag. COD orders go to production immediately; the payment is confirmed only
when delivery is recorded with "cash collected" (partner or admin), which also posts the
ledger entries.

## Cards and aggregators

| Option | Rwanda | Notes |
|---|---|---|
| Stripe | Not supported for Rwandan merchants | – |
| Paystack | Not live in Rwanda | – |
| Flutterwave | BNR-licensed PSP; `mobile_money_rwanda`, cards | Webhook `verif-hash` header + verify API |
| IremboPay | BNR-licensed, PCI-DSS; MTN, Airtel, Visa/Mastercard | Webhook verification to confirm |
| DPO Pay | MTN/Airtel push, cards | Verify via `verifyToken` polling |
| Pesapal | – (verify Rwanda onboarding) | IPN unsigned → `GetTransactionStatus` |
| K-Pay | MoMo, cards, SPENN | Callback unsigned – verify by status query |

Recommendation: add **Flutterwave or IremboPay** as the card provider by implementing
`PaymentGateway` (initiate → hosted payment page redirect; webhook → verify via API).

## Regulatory notes (seek local legal advice)

- **BNR:** Law 061/2021 and Regulation 74/2023 license payment service providers. A
  marketplace that collects buyer funds and pays artists may be providing a payment service.
  Options: obtain a licence, or collect through a licensed PSP with split/payout features.
- **RRA:** VAT 18 %; EBM/e-invoicing requirements for registered taxpayers (integration not
  implemented – see business decisions).
- **Data protection (Law 058/2021):** register with NCSA's Data Protection Office before
  processing; personal data must be stored in Rwanda unless authorised.

## Adding a provider

1. Implement `PaymentGateway` in `app/Services/Payments/Gateways`.
2. Register the key in `GatewayRegistry::GATEWAYS` and `config/payments.php` (and
   `checkout_order`).
3. Add `Http::fake` tests covering initiation, a forged callback, a verified success and an
   amount mismatch (see `tests/Feature/Payments/PaymentFlowTest.php`).
