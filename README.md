# StoreLift

StoreLift turns a creator catalog into an editable storefront preview. Upload your own CSV or import the public JenniNexus products page, review the listings, and approve a version you can export. The separate Creator Demo Kit takes a buyer through PayPal Sandbox checkout to a verified receipt and protected ZIP download.

[Local setup](#run-the-sandbox-storefront) · [Judge walkthrough](#judge-walkthrough) · [Bring your catalog](#bring-your-catalog) · [PayPal resources](docs/PAYPAL.md) · [Development](#development)

## Run the sandbox storefront

To try catalog import first, start the app with `php -S 127.0.0.1:8015 -t public public/index.php` from the repository root and open [the local storefront](http://127.0.0.1:8015/). Configure PayPal to enable the kit purchase.

1. Install PHP 8.3 or newer with `curl`, `dom`, `pdo_sqlite` and `zip` enabled.
2. In [PayPal Developer Apps & Credentials](https://developer.paypal.com/dashboard/applications/sandbox), choose **Sandbox → Create App → Merchant**. Choose a sandbox Business account. Keep a separate sandbox Personal account for the buyer. See [PayPal’s setup guide](https://developer.paypal.com/api/get-started/).
3. On Windows, run `pwsh -NoProfile -File scripts/configure-sandbox.ps1`. Enter the sandbox app’s Client ID and Secret at the hidden prompts. The script generates a download signing key and saves the configuration under your local StoreLift profile. On other systems, copy `.env.example` to `.env.local`, fill in the sandbox values and generate a signing key using the example command. Operators may set `STORELIFT_ENV_FILE` to an external private configuration file. Process environment variables override file settings.
4. Run `php scripts/check-paypal.php` to verify sandbox OAuth. The check reports whether PayPal accepts the app and omits tokens and credential values. On Windows, PHP cURL uses the native certificate store when supported.
5. Run `php scripts/build-demo-kit.php`, then `php -S 127.0.0.1:8015 -t public public/index.php`. On Windows, `pwsh -NoProfile -File scripts/start-local.ps1` performs both steps. Open [StoreLift Sandbox](http://127.0.0.1:8015/).
6. Select the PayPal button and approve payment using the sandbox **Personal** buyer. Open the verified receipt and download the ZIP. Refresh in the same browser to renew an expired link. The visible test amount is denominated in sandbox USD.

## Judge walkthrough

Open the hosted demo supplied with the submission or start StoreLift locally using the setup above. For the full local walkthrough, configure both PayPal Sandbox and the [AI provider](#review-and-approve) before opening the storefront.

1. Enter `https://jenninexus.com/products` under **JenniNexus example URL**, then choose **Import catalog**. You can also start from the [sample CSV](examples/catalog.csv).
2. Choose **Get AI suggestions**. Compare one suggestion with the original description and choose **Use suggested description** if you want to apply it. Select **Approve storefront preview**. The original free, tip, and membership links stay attached to their listings.
3. Follow **Try the Creator Demo Kit checkout** and approve the $3.00 USD test payment with a separate PayPal Sandbox **Personal** buyer. The receipt will show **Sandbox payment verified** and offer **Download Creator Demo Kit**.

Keep the same browser session from approval through download. Use the dedicated test access supplied with a hosted submission. For local setup, use your own authorized provider and Sandbox credentials.

## How checkout works

- StoreLift freezes the SKU, amount, currency, kit version and file hash before creating an order. It records the merchant PayPal assigned, or checks the optional configured merchant ID.
- The [PayPal JavaScript SDK](https://developer.paypal.com/studio/checkout/standard/integrate) handles buyer approval. PHP uses [Orders v2](https://developer.paypal.com/api/orders/v2) to capture and read the result. The server compares the returned order and capture to the saved record before granting access.
- SQLite binds the receipt to the current browser session. The app verifies the signed download link, expiry, entitlement and file hash before sending the ZIP. A saved receipt survives a server restart when its SQLite and session storage persist.
- An HTTPS demo can register `/api/webhook` for `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.REFUNDED` and `PAYMENT.CAPTURE.REVERSED`. Set `PAYPAL_SANDBOX_WEBHOOK_ID` from that registration. StoreLift asks PayPal to verify the signature and stores event IDs to handle retries. A refund or reversal revokes the affected capture. Verify real delivery and revocation in the hosted sandbox before judge access. [PayPal webhook setup](https://developer.paypal.com/api/rest/webhooks/rest/)

For hosting, serve `public/` as the web root and route the app URLs to `public/index.php`. Keep `var/` or `STORELIFT_STATE_DIR` on persistent private storage and set `STORELIFT_HTTPS=1` behind a TLS proxy. The PHP development server is for local QA. The app connects to PayPal Sandbox only. Configuration, receipts and test accounts belong in private storage.

To mount StoreLift under a path such as `/storelift/`, set the PHP worker environment variable `STORELIFT_BASE_PATH=/storelift`. Redirect the bare mount to its trailing-slash URL. Serve the two static assets under that mount, and forward the original request URI for the app's page and API routes to `public/index.php`. The catalog endpoint is `/api/catalog`. The catalog and webhook URLs include the same mount prefix.

## Bring your catalog

**Your own listings.** Copy the [sample CSV](examples/catalog.csv), replace the example rows with your listings, then choose **Import CSV**. Use up to 20 listings in a UTF-8 file under 24 KB. Each row needs a stable lowercase `sku`, a `title`, an `action`, a short `description`, and a public HTTPS `url`. Actions are `free`, `tip`, `membership`, or `link`. Use `link` for an existing item that buyers purchase on another shop.

**A catalog from another shop.** Export product or listing data from Shopify or Etsy and map it to the sample columns. For Gumroad, use the public shop page to find each product title, description and permalink, then add those rows to the sample CSV. The URL importer currently supports the JenniNexus products page. Shopify, Etsy, and Gumroad links entered in the CSV open the original listings. [Shopify product export](https://help.shopify.com/en/manual/products/import-export/export-products) · [Etsy listing export](https://help.etsy.com/hc/en-us/articles/360000343508-How-to-Download-Your-Listing-Information) · [Gumroad product permalinks](https://gumroad.com/help/article/74-the-analytics-dashboard)

**Make it yours.** Copy [`examples/storefront.example.json`](examples/storefront.example.json) to `.local/storefront.json` and edit the name and tagline. Leave `catalog_url` null for a blank example field or set it to the JenniNexus URL for a walkthrough. Store the PayPal and AI keys in your private environment settings separately.

The purchased Creator Demo Kit contains a planning worksheet and launch checklist. Use the repository sample above as the browser import template. The approved storefront is a preview for the current browser, with a JSON export. The Creator Demo Kit is the app's only PayPal Sandbox purchase.

### Review and approve

1. Import your CSV or [the JenniNexus example](https://jenninexus.com/products). The example adapter preserves source statuses and action links, including separate tips and memberships.
2. Review the descriptions and edit them as needed. When AI is configured, **Get AI suggestions** asks for editable descriptions, categories, suggested action labels, and missing-information warnings. The suggestions keep the source destinations and purchase settings intact.
3. Approve the preview. Its revision and JSON export stay available in this browser session. The embeddable card links back to the same server for the separate Sandbox kit checkout.

For example, a suggestion could shorten the PDF Designer copy to “Design PDFs with branded palettes and keep drafts in a private vault,” suggest the category “Document tools,” or ask whether to add setup requirements. This is an illustrative edit. **Use suggested description** copies the model's draft into an editable field. Approval saves your edits. Source destinations, membership and tip classifications, prices, and payment settings stay under the app's existing controls.

AI suggestions require `STORELIFT_AI_ENDPOINT`, `STORELIFT_AI_MODEL` and `STORELIFT_AI_API_KEY` in the private configuration. Use your own authorized API key and a model that supports strict structured outputs. The supported endpoints are listed in [.env.example](.env.example). When you request suggestions, StoreLift sends listing text, statuses, action labels and destination hostnames to that provider. Manual review is available without AI. Configure provider-side usage controls before enabling AI on a public installation. [OpenAI structured outputs](https://developers.openai.com/api/docs/guides/structured-outputs) · [OpenRouter structured outputs](https://openrouter.ai/docs/guides/features/structured-outputs)

## Optional PayPal MCP

The storefront runs with PHP, the PayPal JavaScript SDK and REST credentials. For a separate agent connection to your own sandbox account, see the [PayPal resources](docs/PAYPAL.md#optional-developer-tools) and [example MCP configuration](mcp/paypal-sandbox.example.json).

## Development

Run `php tests/checkout.php` for checkout and download checks with an isolated PayPal gateway. Run `php tests/catalog-import.php`, `php tests/catalog-csv.php` and `php tests/catalog-ai.php` for import and AI-response validation. Use the sandbox and catalog steps above to verify external integrations.

The UI uses PHP templates, vanilla JavaScript and [a single stylesheet](public/assets/store.css). The stylesheet owns the surface, text, focus and motion tokens. It follows the browser color scheme and reduced-motion preference. It needs no asset build.

The Creator Demo Kit source is in `kit/`. StoreLift and the kit are MIT licensed. The PayPal SDK loads directly from PayPal at checkout.
