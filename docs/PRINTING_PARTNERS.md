# Printing partners

## Onboarding a partner

1. **Admin › Printing partners › New** – name, contact, city, daily capacity, priority
   (lower is preferred).
2. **Services** – tick the products the partner can produce and enter the partner's
   **unit cost** and production days for each. The unit cost is what the platform owes the
   partner; it is posted to the ledger when a job is dispatched.
3. **Service areas** – add the countries (and optionally specific delivery zones) the
   partner ships to.
4. **Members** – add staff by e-mail (they must have an account). They receive the
   `partner_staff` role and can sign in to `/partner`.

## Automatic assignment

After payment is verified, `AssignOrderToPartners` assigns each item to the eligible
partner with the lowest open-jobs-to-capacity ratio, then the best priority. Items without
an eligible partner show as **Awaiting printer** in the admin order view, where staff can
assign or reassign manually (an open job at another partner is cancelled and logged).

## Partner workflow (`/partner`)

1. New job → **Accept**
2. **Start printing** – download the original artwork (full resolution) and follow the
   specification: print area, printed width in mm, offsets, rotation and effective DPI.
3. **Quality check** – record *passed* (job becomes *ready for dispatch*) or *failed* with
   notes (job returns to printing; the failure stays in the event log).
4. **Ship** – create a shipment for one or more ready items of the order with carrier and
   tracking number. Items from the same order can ship separately (partial fulfilment).
5. **Tracking events** – in transit, out for delivery, delivered (tick *cash collected* for
   cash-on-delivery orders), failed delivery, returned.
6. **Problems** – report an issue at any time; mark the job failed if it cannot be produced.
   Administrators can then reprint at the same or another partner and choose whether the
   failed attempt's cost is payable.

Partners only see their own jobs and shipments, receive only the recipient's name, phone and
address, and can only download artwork versions they have been assigned to print.

## Integration options

The portal is the default integration. For partners with their own systems, the same
services (`ProductionWorkflow`, `ShipmentService`) can be exposed through authenticated API
endpoints (Sanctum tokens) – not yet implemented.
