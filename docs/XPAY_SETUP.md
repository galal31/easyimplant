# XPay Hosted Checkout setup

The code path is enabled only when all three server environment variables are present:

```text
XPAY_SECRET_KEY=sk_test_...
XPAY_WEBHOOK_SECRET=whsec_...
XPAY_APP_URL=https://your-domain.example
```

`XPAY_APP_URL` must be the public base URL of this project, without a trailing slash. A live key requires HTTPS.

Apply this migration before deploying the PHP changes:

```text
migrations/2026_09_17_xpay_hosted_checkout.sql
```

In the XPay dashboard, create a webhook endpoint for:

```text
https://your-domain.example/api/xpay_webhook.php
```

Subscribe to these events:

```text
checkout.session.completed
checkout.session.async_payment_succeeded
checkout.session.async_payment_failed
checkout.session.expired
```

Use a test API key and test webhook endpoint first. The checkout button creates a Hosted Checkout session for an owned Surgical Guide request in `pending_payment`. The request moves to `in_progress` only after a signed webhook reports `paymentStatus = paid` and the amount, currency, metadata, mode, and stored request total all match.
