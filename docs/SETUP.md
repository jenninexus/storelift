# Setup

StoreLift is a PHP 8.3 app with SQLite state and one local stylesheet. Catalog import, review and approval run without any payment configuration; the Creator Demo Kit checkout needs PayPal Sandbox credentials.

## Requirements

- PHP 8.3 or newer with `curl`, `dom`, `pdo_sqlite` and `zip` enabled.
- A PayPal Sandbox Merchant app, and a **separate** Sandbox Personal account as the buyer.
- Optional: an authorized OpenAI or OpenRouter key for AI suggestions.

## 1. Start it

```bash
php -S 127.0.0.1:8015 -t public public/index.php
```

Run that from the repository root and open <http://127.0.0.1:8015/>. On Windows, `pwsh -NoProfile -File scripts/start-local.ps1` builds the demo kit and starts the server on a free port so PHP leaves room for slow provider responses.

## 2. Create the Sandbox app

In [PayPal Developer Apps & Credentials](https://developer.paypal.com/dashboard/applications/sandbox), choose **Sandbox → Create App → Merchant** and select a Sandbox Business account. Keep a separate Sandbox Personal account for the buyer. See [PayPal's setup guide](https://developer.paypal.com/api/get-started/) and [Standard Checkout](https://developer.paypal.com/studio/checkout/standard/integrate).

## 3. Configure the app

On Windows:

```powershell
pwsh -NoProfile -File scripts/configure-sandbox.ps1
```

Enter the Sandbox app's Client ID and Secret at the hidden prompts. The script generates a download signing key and saves the configuration under your local StoreLift profile.

On other systems, copy `.env.example` to `.env.local`, fill in the Sandbox values, and generate a signing key with the example command in that file. Operators may point `STORELIFT_ENV_FILE` at an external private configuration file. Process environment variables override file settings.

## 4. Verify OAuth

```bash
php scripts/check-paypal.php
```

The check reports whether PayPal accepts the app and omits tokens and credential values. On Windows, PHP cURL uses the native certificate store when supported.

## 5. Build the kit and buy it

```bash
php scripts/build-demo-kit.php
php -S 127.0.0.1:8015 -t public public/index.php
```

Select the PayPal button and approve the $3.00 test payment with the Sandbox **Personal** buyer. Open the verified receipt and download the ZIP. Refresh in the same browser to renew an expired link. The visible amount is denominated in sandbox USD and is not real revenue.

## Hosting

Serve `public/` as the web root and route app URLs to `public/index.php`. Keep `var/` (or `STORELIFT_STATE_DIR`) on persistent private storage and set `STORELIFT_HTTPS=1` behind a TLS proxy. The PHP development server is for local QA only. The app connects to PayPal Sandbox only. Configuration, receipts and test accounts belong in private storage, never in the repository.

## Mounting under a path

To mount under `/storelift/`, set the PHP worker environment variable `STORELIFT_BASE_PATH=/storelift`, redirect the bare mount to its trailing-slash URL, serve the two static assets under that mount, and forward the original request URI for the app's page and API routes to `public/index.php`. The catalog endpoint is `<mount>/api/catalog`; catalog and webhook URLs include the same mount prefix.
