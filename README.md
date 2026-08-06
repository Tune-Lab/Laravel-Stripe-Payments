# Laravel + Stripe: Accepting Payments and Handling Webhooks

**Stack:** PHP 8.3+, Laravel 13, Stripe PHP SDK 13, Pest 3, PostgreSQL, Redis (queues)

## What this is

A complete one-time payment flow through Stripe Checkout: create a payment session, send the customer to Stripe to pay, receive the confirmation back, and mark the order as paid.

It shows how to build a payments module that holds up in production — where notifications arrive more than once, background workers run at the same time, the payment provider is briefly unavailable, and someone may try to fake a "payment received" message.

## Why it's reliable

| What could go wrong | What this example does about it |
|---|---|
| A customer is charged twice on a double-click or network retry | Each payment can only ever go through once |
| Stripe sends the same payment notification more than once | Duplicates are recognized and processed only once |
| Someone sends a fake "payment received" message | Every notification is verified as genuinely from Stripe before it's trusted |
| Two background processes handle the same payment at once | A payment is safely finalized only once, even under heavy load |
| Notifications arrive out of order | A payment can't jump into an impossible state (e.g. "paid" after "failed") |
| The paid amount doesn't match the order | The amount is always checked against your records, and mismatches are flagged for review |
| Rounding errors on money | Money is handled exactly — no floating-point rounding bugs |
| Stripe disables endpoints that respond too slowly | The endpoint replies instantly and does the real work in the background |
| Tests depend on live keys and the network | The payment flow is fully testable without touching real Stripe |

### Architecture

```
HTTP POST /api/checkout-sessions
        │
        ▼
CheckoutController ──► CreateCheckoutSessionAction ──► PaymentGateway (interface)
   (validation)              (use case)                     └─► StripeGateway (SDK)
                                 │
                                 ▼
                        payments (status: pending)


Stripe ──► POST /api/webhooks/stripe
                    │
                    ▼
        StripeWebhookController
          1. verify signature       → 400 if it doesn't match
          2. INSERT into webhook_events → 200 if it's a duplicate
          3. dispatch to the queue
          4. 202 Accepted (< 100 ms)
                    │
                    ▼
        ProcessStripeWebhookEvent (queue)
                    │
                    ▼
        MarkPaymentAsPaidAction → payments (status: paid) → PaymentSucceeded event
```