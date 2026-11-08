# Judge walkthrough

Open the hosted demo supplied with the submission, or start StoreLift locally with [SETUP.md](SETUP.md). For a full local walkthrough, configure both PayPal Sandbox and an [AI provider](CATALOG.md#ai-suggestions) before opening the storefront.

1. Enter `https://jenninexus.com/products` under **JenniNexus example URL**, then choose **Import catalog**. You can also start from the [sample CSV](../examples/catalog.csv).
2. Choose **Get AI suggestions**. Compare one suggestion with the original description and choose **Use suggested description** if you want to apply it. Select **Approve storefront preview**. The original free, tip, and membership links stay attached to their listings.
3. Follow **Try the Creator Demo Kit checkout** and approve the $3.00 USD test payment with a separate PayPal Sandbox **Personal** buyer. The receipt shows **Sandbox payment verified** and offers **Download Creator Demo Kit**.

Keep the same browser session from approval through download. Use the dedicated test access supplied with a hosted submission. For local setup, use your own authorized provider and Sandbox credentials.

## What to look for

| Claim | Where it is visible |
| --- | --- |
| Import is real, not a fixture | Eight listings appear with their source titles, links, and free/tip/membership actions intact. |
| AI is real and bounded | One request produces a description, a category and any missing-information warning; nothing changes until you accept it. |
| Approval is authoritative | Export, embed and checkout all read the approved revision found in `c60ff6e7a17d9ff2`-style revision IDs, not the draft. |
| Payment is verified server-side | The receipt names the capture, and the ZIP link is session-bound and expires. |
| Failure is honest | Unsupported URLs, unavailable AI and cancelled payments produce explicit messages instead of a fake success. |
