# StoreLift

StoreLift is a standalone creator storefront prototype for the PayPal AI Hackathon. Read [README.md](README.md) for setup and [docs/PAYPAL.md](docs/PAYPAL.md) for integration references. The application uses PHP 8.3+, SQLite and vanilla JavaScript.

- Keep the PayPal integration in sandbox. Use separate sandbox buyer and merchant accounts.
- Follow the [PayPal example command](.claude/commands/paypal.example.md) for checkout and optional MCP setup. Each installation supplies its own sandbox credentials. Keep operator-specific pointers in `.local/paypal.md`.
- Serve `public/` as the only web root. Keep credentials, session state, database files and deliverable ZIPs outside it and out of Git.
- Price, currency, product version, recipient and capture verification belong on the server. Preserve session binding, idempotency and refund revocation when changing checkout.
- Run the checkout acceptance checks before changing payment or delivery behavior. Clearly distinguish isolated tests from a real sandbox purchase.
- Keep operator notes outside public exports. Record reproducible setup and integration details in the public documentation.
- Use local checks and Git hooks. Public publishing and demo deployment have separate review gates.
