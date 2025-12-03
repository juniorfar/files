# Flask microservice exposing /payout and /webhook endpoints.
# Run with: FLASK_APP=app.py flask run --host=0.0.0.0 --port=5000
# In production use gunicorn or another WSGI server.

from flask import Flask, request, jsonify
from paypal_client_py import PayPalClient
import os
import json

app = Flask(__name__)
client = PayPalClient()  # uses config/paypal_py.py

@app.route('/health', methods=['GET'])
def health():
    return jsonify({'ok': True})

@app.route('/payout', methods=['POST'])
def payout():
    """
    Secure this endpoint (e.g., with an API key or mutual TLS). This demo expects your server
    to call it, not public clients.
    Body JSON: { "paypalEmail": "...", "amount": 1.23, "currency": "USD", "note": "optional" }
    """
    data = request.get_json(force=True, silent=True)
    if not data:
        return jsonify({'success': False, 'message': 'Invalid JSON'}), 400
    email = data.get('paypalEmail')
    amount = data.get('amount')
    currency = data.get('currency') or client.config.get('currency', 'USD')
    note = data.get('note', 'Payout from site')

    # Basic server-side validation
    if not email or not isinstance(amount, (int, float, str)):
        return jsonify({'success': False, 'message': 'Invalid parameters'}), 400
    try:
        amount_val = float(amount)
        if amount_val <= 0 or amount_val < float(client.config.get('min_payout', 1.0)):
            return jsonify({'success': False, 'message': 'Amount too small'}), 400
    except Exception:
        return jsonify({'success': False, 'message': 'Invalid amount'}), 400

    # IMPORTANT: In production, check user balance and reserve funds BEFORE calling PayPal.
    status, resp = client.create_payout(email, amount_val, currency=currency, note=note)
    if status >= 200 and status < 300:
        return jsonify({'success': True, 'paypal_response': resp}), 201
    else:
        return jsonify({'success': False, 'paypal_response': resp}), 500

@app.route('/webhook', methods=['POST'])
def webhook():
    # Get raw body and headers
    raw = request.get_data(as_text=True)
    # extract relevant headers into a dict
    headers = {
        'PayPal-Transmission-Id': request.headers.get('PayPal-Transmission-Id') or request.headers.get('PAYPAL-TRANSMISSION-ID'),
        'PayPal-Transmission-Time': request.headers.get('PayPal-Transmission-Time'),
        'PayPal-Transmission-Sig': request.headers.get('PayPal-Transmission-Sig'),
        'PayPal-Cert-Url': request.headers.get('PayPal-Cert-Url'),
        'PayPal-Auth-Algo': request.headers.get('PayPal-Auth-Algo'),
    }
    try:
        verify_resp = client.verify_webhook_signature(headers, raw)
    except Exception as e:
        # log error
        app.logger.exception("Webhook verification failed")
        return "Verification failed", 400

    # verify_resp typically contains verification_status: "SUCCESS"
    if verify_resp.get('verification_status') != 'SUCCESS':
        app.logger.warning("Webhook failed verification: %s", verify_resp)
        return "Invalid signature", 400

    event = request.get_json()
    # handle relevant events (e.g., PAYMENT.PAYOUTS-ITEM.*)
    # In production update your DB payout record using sender_item_id or payout_item_id
    app.logger.info("Received verified webhook: %s", event.get('event_type'))
    # For demo, write to a small file
    try:
        out_path = os.path.join(os.path.dirname(__file__), 'data')
        os.makedirs(out_path, exist_ok=True)
        with open(os.path.join(out_path, 'last_webhook.json'), 'w') as f:
            json.dump(event, f, indent=2)
    except Exception:
        pass

    return "OK", 200

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=int(os.environ.get('PORT', 5000)))