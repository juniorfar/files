```markdown
# PayPal Payouts + Webhook Flask Microservice (sandbox-ready)

Overview
This microservice demonstrates:
- obtaining OAuth2 client credentials token and caching it;
- creating PayPal REST Payouts (single-recipient example);
- verifying incoming PayPal webhooks using the verify-webhook-signature endpoint.

Prerequisites
- Python 3.7+
- PayPal REST app: client_id and client_secret (sandbox first)
- Registered webhook in PayPal Developer Dashboard (copy webhook_id into config/paypal_py.py)

Install
1. Create a virtualenv and activate it:
   python -m venv venv
   source venv/bin/activate      # on Windows: venv\Scripts\activate

2. Install requirements:
   pip install -r requirements.txt

(Optionally install official SDK)
   pip install paypal-server-sdk==1.1.0

Configure
1. Copy config/paypal_py.example.py to config/paypal_py.py and set:
   - client_id
   - client_secret
   - env = 'sandbox' (for testing)
   - webhook_id (from PayPal dashboard)
   - token_cache_file (optional)

Run (development)
   export FLASK_APP=app.py
   flask run --host=0.0.0.0 --port=5000

Endpoints
- POST /payout
  Body: { "paypalEmail": "...", "amount": 1.23, "currency": "USD", "note": "optional" }
  Note: Secure this endpoint in production. You should run balance check & reservation in your main app before calling this.

- POST /webhook
  Register this URL in PayPal (must be HTTPS in production).
  This endpoint validates the PayPal webhook signature and writes the event to data/last_webhook.json (demo).

Token caching and persistence
- By default token caching is file-based under sys temp dir. Use paypal_token_store.TokenStore.on_update to persist tokens to DB (the SDK docs show a similar callback pattern).

Using the official PayPal SDK
- If you prefer the official paypal-server-sdk to manage OAuth tokens, initialize it as the SDK docs show:
  from PaypalServersdkClient import PaypalServersdkClient
  from PaypalServersdkClient.models import ClientCredentialsAuthCredentials

  client = PaypalServersdkClient(
    client_credentials_auth_credentials=ClientCredentialsAuthCredentials(
      o_auth_client_id='CLIENT_ID',
      o_auth_client_secret='CLIENT_SECRET',
      o_auth_on_token_update=(lambda t: save_token(t))
    ),
    environment=Environment.SANDBOX
  )

  The SDK will automatically fetch tokens when you call endpoints and will call your on_token_update callback when tokens change.

Security & production notes
- Require server-side authentication and balance checks before issuing payouts.
- Use HTTPS for webhook and payout endpoints.
- Use DB-based token persistence and lock/update carefully to avoid race conditions.
- Use strong idempotency keys (PayPal-Request-Id) stored in DB to prevent duplicate payouts.
- Log and reconcile webhook events to update your payout records.
- Always test thoroughly in sandbox, then switch to live client credentials.

If you want, I can:
- Convert the code to use paypal-server-sdk library calls for payouts & webhook verification if you prefer the SDK usage.
- Add DB integration for token storage and to correlate payouts with your main app's payouts table.
- Add production-ready deployment notes (gunicorn, systemd, TLS, monitoring).
```