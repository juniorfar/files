```markdown
# PayPal Webhook Handler (summary)

This adds a webhook endpoint for PayPal events and a small PayPal client helper for:
- obtaining OAuth2 access tokens (file-cached)
- verifying webhook signatures with PayPal's verify-webhook-signature endpoint

Files added:
- api/paypal_client.php — helper functions (getAccessToken, verifyWebhookSignature)
- api/webhook.php — webhook endpoint you should register in PayPal
- config/paypal.example.php — updated to include webhook_id and token cache filename
- data/payouts.json — created automatically by webhook handler when first event arrives
- api/webhook.log — runtime log file created/updated by webhook handler

Registering the webhook:
1. Go to PayPal Developer Dashboard -> My Apps & Credentials -> [Your App] -> Webhooks.
2. Add the webhook URL: https://yourdomain.example/api/webhook.php
3. Subscribe to events: at least PAYMENT.PAYOUTS-ITEM.* and PAYMENT.PAYOUTS-ITEM.SUCCEEDED/FAILED/UNCLAIMED/REFUNDED
4. Copy the provided webhook ID into config/paypal.php (webhook_id).

Testing:
- Use PayPal sandbox webhook simulator (Developer Dashboard) to send a SAMPLE payout event or use curl to replay an actual event payload.
- Confirm the webhook handler returns HTTP 200 and you see entries in api/webhook.log and data/payouts.json.

Security and production notes:
- Use HTTPS for the webhook endpoint.
- Verify webhook signatures as implemented here.
- Persist payout records in your database and mark them pending before calling PayPal Payouts; on webhook update the DB record state.
- Replace file-based caching and JSON storage with your production datastore.
- Implement retries or idempotency on receiving duplicate notifications.
```