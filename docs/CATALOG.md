# Catalogs, AI suggestions and approval

## Your own listings

Copy the [sample CSV](../examples/catalog.csv), replace the example rows with your listings, then choose **Import CSV**. Use up to 20 listings in a UTF-8 file under 24 KB. Each row needs a stable lowercase `sku`, a `title`, an `action`, a short `description` and a public HTTPS `url`. Actions are `free`, `tip`, `membership` or `link`. Use `link` for an existing item that buyers purchase on another shop.

## A catalog from another shop

Export product or listing data from Shopify or Etsy and map it to the sample columns. For Gumroad, use the public shop page to find each product title, description and permalink, then add those rows to the sample CSV. The URL importer currently supports the JenniNexus products page only. Shopify, Etsy and Gumroad URLs entered in the CSV open the original listings.

[Shopify product export](https://help.shopify.com/en/manual/products/import-export/export-products) · [Etsy listing export](https://help.etsy.com/hc/en-us/articles/360000343508-How-to-Download-Your-Listing-Information) · [Gumroad product permalinks](https://gumroad.com/help/article/74-the-analytics-dashboard)

## Make it yours

Copy [`examples/storefront.example.json`](../examples/storefront.example.json) to `.local/storefront.json` and edit the name and tagline. Leave `catalog_url` null for a blank example field, or set it to the JenniNexus URL for a walkthrough. Store the PayPal and AI keys in your private environment settings separately.

## Review and approve

1. Import your CSV or [the JenniNexus example](https://jenninexus.com/products). The example adapter preserves source statuses and action links, including separate tips and memberships.
2. Review the descriptions and edit them as needed. When AI is configured, **Get AI suggestions** asks for editable descriptions, categories, suggested action labels and missing-information warnings. Suggestions keep source destinations and purchase settings intact.
3. Approve the preview. Its revision and JSON export stay available in this browser session. The embeddable card links back to the same server for the separate Sandbox kit checkout.

For example, a suggestion could shorten the PDF Designer copy to "Design PDFs with branded palettes and keep drafts in a private vault", suggest the category "Document tools", or ask whether to add setup requirements. This is an illustrative edit. **Use suggested description** copies the model's draft into an editable field. Approval saves your edits. Source destinations, membership and tip classifications, prices and payment settings stay under the app's existing controls.

## AI suggestions

AI suggestions require `STORELIFT_AI_ENDPOINT`, `STORELIFT_AI_MODEL` and `STORELIFT_AI_API_KEY` in the private configuration. Use your own authorized API key and a model that supports strict structured outputs; the supported endpoints are listed in [.env.example](../.env.example).

When you request suggestions, StoreLift sends listing text, statuses, action labels and destination hostnames to that provider. Manual review is available without AI. Configure provider-side usage controls before enabling AI on a public installation.

[OpenAI structured outputs](https://developers.openai.com/api/docs/guides/structured-outputs) · [OpenRouter structured outputs](https://openrouter.ai/docs/guides/features/structured-outputs)

## What AI may not do

Suggestions never change a source URL, an action type, a payee or a price, and AI output cannot publish a catalog revision. Approval is the only write path, and it is the merchant's action.
