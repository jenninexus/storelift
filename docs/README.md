# StoreLift docs

Every detail that used to live on the landing page is here. Start with the README for the product summary.

| Doc | Read it when |
| --- | --- |
| [SETUP.md](SETUP.md) | You need PHP extensions, a PayPal Sandbox app, a signing key, a local server or a mount under a path. |
| [JUDGE-WALKTHROUGH.md](JUDGE-WALKTHROUGH.md) | You have a fresh browser and ten minutes. |
| [CATALOG.md](CATALOG.md) | You are bringing a CSV, mapping another shop's export, or configuring AI suggestions. |
| [CHECKOUT.md](CHECKOUT.md) | You are reading the order, capture, webhook, entitlement and download logic. |
| [PAYPAL.md](PAYPAL.md) | You want PayPal's own integration and developer-tool references. |
| [DEVELOPMENT.md](DEVELOPMENT.md) | You are changing PHP source, tests, tokens or the demo kit. |
| [JOURNEY.md](JOURNEY.md) | You want the dated verification record behind each claim. Local evidence log; not part of the public release. |
| [../AGENTS.md](../AGENTS.md) | You are an agent or contributor orienting in the repository. |

## Shape of the app

| Path | Role |
| --- | --- |
| `public/index.php` | Web entry point; routes pages and `/api/*`. |
| `public/assets/store.css` | The only stylesheet. Owns surface, text, focus and motion tokens. |
| `public/assets/app.js` | Vanilla JavaScript for import, review, approval and checkout state. |
| `src/` | PHP domain code: catalog import, CSV, AI adapter, review, checkout, orders, downloads, config. |
| `examples/` | Importable sample CSV and storefront profile example. |
| `kit/` | Source for the purchased Creator Demo Kit. |
| `scripts/` | Build, Sandbox configuration, OAuth check and local launcher. |
| `tests/` | Four PHP test suites (checkout, import, CSV, AI response). |
| `mcp/` | Optional PayPal Sandbox MCP configuration example. |

## Visual profile

Runtime design values live in `public/assets/store.css`, mirrored from the www-theme-kit `storelift` profile: ink-violet panels, lavender controls, ice focus, and an Aesyna spectrum edge used as a static decorative rule. There is no kit import and no build step at runtime.
