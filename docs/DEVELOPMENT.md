# Development

## Tests

| Test | Covers |
| --- | --- |
| `php tests/checkout.php` | Checkout and download with an isolated PayPal gateway fixture. |
| `php tests/catalog-import.php` | Supported-URL import and its boundaries. |
| `php tests/catalog-csv.php` | CSV parsing, limits and validation. |
| `php tests/catalog-ai.php` | AI response validation and rejection paths. |

Use the Sandbox and catalog steps to verify external integrations; the fixtures cover edge cases only.

## Source layout

| Path | Contents |
| --- | --- |
| `src/Catalog.php` | Listing model and normalization. |
| `src/CatalogCsv.php`, `src/CatalogImport.php` | CSV and URL import. |
| `src/CatalogAi.php` | Provider adapter with strict structured output. |
| `src/CatalogReview.php` | Drafts, approval and revision identity. |
| `src/Checkout.php`, `src/OrderStore.php`, `src/PayPalClient.php`, `src/PayPalGateway.php` | Order creation, capture, persistence and Sandbox client. |
| `src/Downloads.php` | Signed links, expiry, entitlements and file hashes. |
| `src/Config.php`, `src/bootstrap.php` | Configuration, environment and wiring. |
| `src/storefront.php`, `src/catalog-view.php` | Server-rendered views. |

## UI

The UI uses PHP templates, vanilla JavaScript and [a single stylesheet](../public/assets/store.css). The stylesheet owns the surface, text, focus and motion tokens, follows the browser color scheme and reduced-motion preference, and needs no asset build. There is no CDN or theme-kit import at runtime.

## Demo kit

The Creator Demo Kit source is in `kit/` and ships a catalog template, a launch checklist and a README. `scripts/build-demo-kit.php` produces the ZIP that checkout delivers; its SHA-256 is recorded at build time and compared before download.

The purchased kit contains a planning worksheet and a launch checklist. The repository's `examples/catalog.csv` is the browser import template. The Creator Demo Kit is the app's only PayPal Sandbox purchase.

## License

StoreLift and the kit are MIT licensed. The PayPal SDK loads directly from PayPal at checkout.
