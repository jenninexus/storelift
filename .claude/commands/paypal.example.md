---
description: Set up and verify StoreLift PayPal Sandbox checkout and the optional PayPal MCP connection.
---

# StoreLift PayPal Sandbox

1. Open Apps & Credentials in the PayPal Developer Dashboard and select **Sandbox**. Use a **Merchant** REST app linked to a sandbox Business seller. Use a separate sandbox Personal buyer for the approval step. [PayPal REST setup](https://developer.paypal.com/api/rest/)
2. Configure your app using [`.env.example`](../../.env.example) or the Windows setup helper in the [README](../../README.md). Keep the secret and the download signing key in private configuration. Run `php scripts/check-paypal.php` for a credential check that reports only success or failure.
3. Build the kit, open the local storefront, and approve one sandbox purchase. Verify the capture receipt and match the downloaded ZIP to the SHA-256 printed by `php scripts/build-demo-kit.php`. Use the server-side capture state as payment evidence.
4. For a hosted sandbox, add its public HTTPS `/api/webhook` URL to this same Sandbox app. Subscribe to `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.REFUNDED`, and `PAYMENT.CAPTURE.REVERSED`. Save the webhook ID privately as `PAYPAL_SANDBOX_WEBHOOK_ID`. Check real signed delivery and confirm that a refund revokes the download. Local synchronous checkout can leave the webhook ID empty. [PayPal webhook setup](https://developer.paypal.com/api/rest/webhooks/rest/)

## Optional MCP client

Use PayPal's sandbox SSE endpoint `https://mcp.sandbox.paypal.com/sse`. For clients with native SSE support, copy the [example](../../mcp/paypal-sandbox.example.json) into a private workspace config or merge its `paypal-sandbox` entry into your existing config. Use the client's OAuth flow with your sandbox account. For clients requiring a transport bridge, use the `mcp-remote` SSE configuration in PayPal's quickstart. [PayPal MCP quickstart](https://developer.paypal.com/ai-tools/mcp-server/)

Start with tool discovery and a read-only sandbox lookup. The MCP connection is an operator tool. StoreLift still creates, captures and reconciles orders through its REST integration. Use a local `.local/paypal.md` for operator-specific pointers and keep actual credentials in private configuration.
