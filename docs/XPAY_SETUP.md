# XPay Hosted Checkout setup

Copy the tracked example to the ignored local configuration file:

```text
config/xpay.local.example.php -> config/xpay.local.php
```

Set the three values in `config/xpay.local.php`. Use an XPay secret test key that starts with `sk_test_`; do not use `pk_test_` or `rk_test_`:

```php
<?php

return [
    'XPAY_SECRET_KEY' => 'sk_test_...',
    'XPAY_WEBHOOK_SECRET' => 'whsec_...',
    'XPAY_APP_URL' => 'http://localhost/easyimplant',
];
```

Each non-empty value in `config/xpay.local.php` takes priority. If a value is empty or the local file is absent, the matching server environment variable is used as a fallback:

```text
XPAY_SECRET_KEY=sk_test_...
XPAY_WEBHOOK_SECRET=whsec_...
XPAY_APP_URL=https://your-domain.example
```

`config/xpay.local.php` is excluded from Git, and Apache access to the `config` directory is denied by `config/.htaccess`. Never commit the real secret key or webhook signing secret.

`XPAY_APP_URL` must be the base URL of this project, without a trailing slash. Use the production HTTPS URL after deployment. A live key requires HTTPS.

Apply this migration before deploying the PHP changes:

```text
migrations/2026_09_17_xpay_hosted_checkout.sql
```

In the XPay dashboard, open `Developers -> Webhooks -> Add endpoint` and create a webhook endpoint for:

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

Copy the endpoint Signing Secret, which normally starts with `whsec_`, into `XPAY_WEBHOOK_SECRET` in `config/xpay.local.php`.

Use a test API key and test webhook endpoint first. The checkout button creates a Hosted Checkout session for an owned Surgical Guide request in `pending_payment`. The request moves to `in_progress` only after a signed webhook reports `paymentStatus = paid` and the amount, currency, metadata, mode, and stored request total all match.
