# Simple token store with optional on_update callback.
# You can replace this with DB-backed storage.

import os
import json
import time
import sys
from pathlib import Path

def get_cache_path(filename):
    tmp = Path(os.getenv('TMPDIR') or os.getenv('TMP') or os.getenv('TEMP') or '/tmp')
    return tmp / filename

class TokenStore:
    def __init__(self, cache_filename='paypal_py_token_cache.json', on_update=None):
        self.cache_path = get_cache_path(cache_filename)
        self.on_update = on_update  # callback invoked with token dict on update

    def load(self):
        try:
            raw = self.cache_path.read_text()
            data = json.loads(raw)
            # expire margin 10s
            if 'expires_at' in data and data['expires_at'] > time.time() + 10:
                return data
        except Exception:
            pass
        return None

    def save(self, token_response):
        """
        token_response should be a dict with keys: access_token, expires_in (seconds), token_type, scope (optional)
        We'll convert to { access_token, expires_at } and write.
        """
        obj = {
            'access_token': token_response.get('access_token'),
            'expires_at': int(time.time()) + int(token_response.get('expires_in', 0)),
            'raw': token_response
        }
        try:
            self.cache_path.write_text(json.dumps(obj, indent=2))
        except Exception:
            # best-effort; in production use DB and proper locking
            pass
        if callable(self.on_update):
            try:
                self.on_update(obj)
            except Exception:
                pass
        return obj