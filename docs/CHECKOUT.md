# Checkout and delivery

StoreLift sells exactly one item: the Creator Demo Kit, at a Sandbox test price.

## Order creation

StoreLift freezes the SKU, amount, currency, kit version and file hash before creating an order. It records the merchant PayPal assigned, or checks the optional configured merchant ID.

## Approval and capture

The [PayPal JavaScript SDK](https://developer.paypal.com/studio/checkout/standard/integrate) handles buyer approval. PHP uses [Orders v2](https://developer.paypal.com/api/orders/v2) to capture and read the result. The server compares the returned order and capture with the saved record before granting access.

## Receipt and download

SQLite binds the receipt to the current browser session. The app verifies the signed download link, expiry, entitlement and file hash before sending the ZIP. A saved receipt survives a server restart when its SQLite and session storage persist.

## Webhooks

An HTTPS installation can register `/api/webhook` for `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.REFUNDED` and `PAYMENT.CAPTURE.REVERSED`. Set `PAYPAL_SANDBOX_WEBHOOK_ID` from that registration. StoreLift asks PayPal to verify the signature and stores event IDs to handle retries. A refund or reversal revokes the affected capture. Verify real delivery and revocation in your hosted Sandbox before judge access.

[PayPal webhook setup](https://developer.paypal.com/api/rest/webhooks/rest/)

## Failure rules

| Situation | Result |
| --- | --- |
| Cancelled or declined approval | No order record is granted access; the storefront reports the failure. |
| Amount, currency or payee mismatch | Capture is refused and no entitlement is issued. |
| Duplicate capture, webhook or reload | One entitlement is reused. |
| Expired download link | HTTP 403; refresh the receipt in the same browser. |
| Refund or reversal | The capture's entitlement is revoked; existing links stop working. |
| Different browser or session | No access to another buyer's receipt or ZIP. |
