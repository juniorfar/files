# Lightweight PayPal helper using requests for token, payouts, and webhook verification.
# Uses paypal_token_store.TokenStore to cache tokens; you can replace with DB-based store.

import time
import requests
import uuid
import json
from pathlib import Path

from paypal_token_store import TokenStore
# load config from file path relative to this file
import os
CONFIG_PATH = os.path.join(os.path.dirname(__file__), 'config', 'paypal_py.py')

def load_config():
    cfg = {}
    if os.path.exists(CONFIG_PATH):
        cfg = {}
        # config file expected to define PAYPAL dict
        with open(CONFIG_PATH, 'r') as f:
            code = f.read()
        # evaluate in safe namespace
        ns = {}
        exec(code, {}, ns)
        cfg = ns.get('PAYPAL', {})
    else:
        # fallback: try example
        from config.paypal_py_example import PAYPAL as cfg_example  # if you copied example
        cfg = cfg_example
    return cfg

class PayPalClient:
    def __init__(self, config=None, token_store=None):
        self.config = config or load_config()
        self.env = self.config.get('env', 'sandbox')
        self.api_base = self.config.get('api_base', {}).get(self.env)
        self.client_id = self.config.get('client_id')
        self.client_secret = self.config.get('client_secret')
        if not token_store:
            self.token_store = TokenStore(cache_filename=self.config.get('token_cache_file', 'paypal_py_token_cache.json'),
                                          on_update=self._on_token_update)
        else:
            self.token_store = token_store

    def _on_token_update(self, token_obj):
        # Default callback: write a small debug file next to config; replace with DB save in production.
        try:
            debug_path = Path(__file__).parent / 'paypal_token_debug.json'
            debug_path.write_text(json.dumps(token_obj, indent=2))
        except Exception:
            pass

    def _fetch_token(self):
        token_url = f"{self.api_base}/v1/oauth2/token"
        resp = requests.post(token_url,
                             data={'grant_type': 'client_credentials'},
                             auth=(self.client_id, self.client_secret),
                             headers={'Accept': 'application/json'})
        if resp.status_code != 200:
            raise Exception(f"Failed to obtain access token: {resp.status_code} {resp.text}")
        tr = resp.json()
        return tr

    def get_access_token(self):
        cached = self.token_store.load()
        if cached and 'access_token' in cached:
            return cached['access_token']
        tr = self._fetch_token()
        saved = self.token_store.save(tr)
        return saved['access_token']

    def create_payout(self, receiver_email, amount, currency='USD', note='Payout from site', sender_batch_id=None, sender_item_id=None):
        if sender_batch_id is None:
            sender_batch_id = str(uuid.uuid4())
        if sender_item_id is None:
            sender_item_id = str(uuid.uuid4())
        access_token = self.get_access_token()
        payout_url = f"{self.api_base}/v1/payments/payouts"
        body = {
            "sender_batch_header": {
                "sender_batch_id": sender_batch_id,
                "email_subject": "You have a payout!",
                "email_message": "You have received a payout."
            },
            "items": [
                {
                    "recipient_type": "EMAIL",
                    "amount": {
                        "value": f"{float(amount):.2f}",
                        "currency": currency
                    },
                    "receiver": receiver_email,
                    "note": note,
                    "sender_item_id": sender_item_id
                }
            ]
        }
        headers = {
            'Content-Type': 'application/json',
            'Authorization': f'Bearer {access_token}',
            'PayPal-Request-Id': str(uuid.uuid4())  # idempotency
        }
        resp = requests.post(payout_url, headers=headers, json=body, timeout=30)
        return resp.status_code, resp.json() if resp.content else {}

    def verify_webhook_signature(self, headers, raw_body):
        """
        headers: dict-like containing PayPal-Transmission-Id, PayPal-Transmission-Time,
                 PayPal-Transmission-Sig, PayPal-Cert-Url, PayPal-Auth-Algo
        raw_body: raw JSON string body
        """
        access_token = self.get_access_token()
        url = f"{self.api_base}/v1/notifications/verify-webhook-signature"
        payload = {
            'transmission_id': headers.get('PayPal-Transmission-Id') or headers.get('PAYPAL-TRANSMISSION-ID'),
            'transmission_time': headers.get('PayPal-Transmission-Time') or headers.get('PAYPAL-TRANSMISSION-TIME'),
            'cert_url': headers.get('PayPal-Cert-Url') or headers.get('PAYPAL-CERT-URL'),
            'auth_algo': headers.get('PayPal-Auth-Algo') or headers.get('PAYPAL-AUTH-ALGO'),
            'transmission_sig': headers.get('PayPal-Transmission-Sig') or headers.get('PAYPAL-TRANSMISSION-SIG'),
            'webhook_id': self.config.get('webhook_id'),
            'webhook_event': json.loads(raw_body)
        }
        resp = requests.post(url, headers={'Content-Type': 'application/json', 'Authorization': f'Bearer {access_token}'}, json=payload, timeout=20)
        if resp.status_code != 200:
            raise Exception(f"Webhook verify failed: {resp.status_code} {resp.text}")
        return resp.json()