# PayPal resources

StoreLift uses the PayPal JavaScript SDK for buyer approval and the server-side Orders v2 API for creating and capturing Sandbox orders. Its PHP webhook verifies PayPal signatures before changing download access. Catalog suggestions use the configured model provider, and the operator reviews the draft before approval. Start with the [StoreLift setup](../README.md#run-the-sandbox-storefront).

## Checkout and testing

| Resource | Use it for |
| --- | --- |
| [PayPal Developer Dashboard](https://developer.paypal.com/dashboard/applications/sandbox) | Create a Sandbox Merchant app and manage its credentials. |
| [PayPal Sandbox](https://developer.paypal.com/api/rest/sandbox/) | Set up separate Business seller and Personal buyer accounts. |
| [Standard Checkout](https://developer.paypal.com/studio/checkout/standard/integrate) and [Orders v2](https://developer.paypal.com/api/orders/v2) | Follow browser approval and server order creation and capture. |
| [Payments v2](https://developer.paypal.com/api/payments/v2) and [webhook setup](https://developer.paypal.com/api/rest/webhooks/rest/) | Test refunds and configure signed event delivery for an HTTPS installation. |

## Optional developer tools

PayPal's AI Toolkit and MCP are developer resources. StoreLift checkout and catalog review run with the credentials and provider settings documented in the README.

| Resource | Use it for |
| --- | --- |
| [PayPal AI documentation](https://docs.paypal.ai/) | Find PayPal's AI tooling and integration guides. |
| [PayPal AI Toolkit on GitHub](https://github.com/paypal/AI-Toolkit) | Inspect PayPal's developer plugin and setup options. |
| [Agent Toolkit quickstart](https://docs.paypal.ai/developer/tools/ai/agent-toolkit-quickstart) | Set up PayPal tools in an agent application. |
| [MCP quickstart](https://docs.paypal.ai/developer/tools/ai/mcp-quickstart) | Connect a compatible MCP client to PayPal. See the [StoreLift example](../mcp/paypal-sandbox.example.json). |
| [Agent tools reference](https://docs.paypal.ai/developer/tools/ai/agent-tools-ref) | Check available tools and their inputs. |

## Hackathon

StoreLift started as a project for the 2026 PayPal AI Hackathon. [PayPal announcement](https://developer.paypal.com/community/blog/PayPal_AI_Hackathon/) · [Devpost](https://paypalaihackathon.devpost.com/)
