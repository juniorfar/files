from flask import Flask, request, jsonify, abort
from paypal_client_py import PayPalClient
import os, json

app = Flask(__name__)
client = PayPalClient()

# Read API key from env or config (prefer env)
API_KEY = os.getenv('PY_MS_API_KEY') or (client.config.get('py_ms_api_key') if hasattr(client, 'config') else None)
if not API_KEY:
    app.logger.warning("PY_MS_API_KEY is not set. Set it in environment for production protection.")

def require_api_key():
    # Accept header X-Api-Key or Authorization: ApiKey <key>
    header_key = request.headers.get('X-Api-Key') or ''
    # Also allow Authorization: ApiKey <token> for flexibility
    auth_hdr = request.headers.get('Authorization', '')
    if auth_hdr.startswith('ApiKey '):
        header_key = auth_hdr.split(' ', 1)[1].strip()
    if not API_KEY or header_key != API_KEY:
        # Fail fast; return 401
        abort(401, description='Invalid API key')

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'ok': True})

@app.route('/payout', methods=['POST'])
def payout():
    # Only server-to-server calls allowed; require API key
    require_api_key()

    data = request.get_json(force=True, silent=True)
    if not data:
        return jsonify({'success': False, 'message': 'Invalid JSON'}), 400

    email = data.get('paypalEmail')
    amount = data.get('amount')
    currency = data.get('currency') or client.config.get('currency', 'USD')
    note = data.get('note', 'Payout from site')

    # Basic validation
    if not email or not isinstance(amount, (int, float, str)):
        return jsonify({'success': False, 'message': 'Invalid parameters'}), 400
    try:
        amount_val = float(amount)
        if amount_val <= 0 or amount_val < float(client.config.get('min_payout', 1.0)):
            return jsonify({'success': False, 'message': 'Amount too small'}), 400
    except Exception:
        return jsonify({'success': False, 'message': 'Invalid amount'}), 400

    # IMPORTANT: Your PHP app must verify and reserve user balance before calling this endpoint.
    status, resp = client.create_payout(email, amount_val, currency=currency, note=note)
    if status >= 200 and status < 300:
        return jsonify({'success': True, 'paypal_response': resp}), 201
    else:
        return jsonify({'success': False, 'paypal_response': resp}), 500

@app.route('/webhook', methods=['POST'])
def webhook():
    # Webhook verification should be public (PayPal posts here). Do NOT require API key.
    raw = request.get_data(as_text=True)
    headers = {
        'PayPal-Transmission-Id': request.headers.get('PayPal-Transmission-Id'),
        'PayPal-Transmission-Time': request.headers.get('PayPal-Transmission-Time'),
        'PayPal-Transmission-Sig': request.headers.get('PayPal-Transmission-Sig'),
        'PayPal-Cert-Url': request.headers.get('PayPal-Cert-Url'),
        'PayPal-Auth-Algo': request.headers.get('PayPal-Auth-Algo'),
    }
    try:
        verify_resp = client.verify_webhook_signature(headers, raw)
    except Exception as e:
        app.logger.exception("Webhook verification failed")
        return "Verification failed", 400

    if verify_resp.get('verification_status') != 'SUCCESS':
        app.logger.warning("Webhook failed verification: %s", verify_resp)
        return "Invalid signature", 400

    event = request.get_json()
    app.logger.info("Received verified webhook: %s", event.get('event_type'))
    # TODO: process webhook and update DB
    # For demo, write a copy to data/last_webhook.json
    try:
        out_path = os.path.join(os.path.dirname(__file__), '..', 'data')
        os.makedirs(out_path, exist_ok=True)
        with open(os.path.join(out_path, 'last_webhook.json'), 'w') as f:
            json.dump(event, f, indent=2)
    except Exception:
        pass

    return "OK", 200

if __name__ == '__main__':
    # Do not bind to 0.0.0.0 in production. Use gunicorn that binds to 127.0.0.1.
    app.run(host='127.0.0.1', port=int(os.environ.get('PY_MS_PORT', 5000)))