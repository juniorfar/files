# Deployment guide for file.match3onlinerewareds.gamer.gd

This document explains how to replace the demo server and deploy the production server for the domain file.match3onlinerewareds.gamer.gd.

1) DNS
- Create an A record for file.match3onlinerewareds.gamer.gd that points to your server's public IP.
- If you're behind a CDN (Cloudflare), add the host there and enable proxying (Full strict recommended).

2) Server preparation (Ubuntu example)
- Install needed packages:
  sudo apt update
  sudo apt install nginx certbot python3-venv python3-pip php-fpm php-mysql mysql-server

3) Create directories
- Web root: /var/www/file.match3onlinerewareds.gamer.gd/public
- File storage: /var/www/file.match3onlinerewareds.gamer.gd/files
- Python microservice: /var/www/file.match3onlinerewareds.gamer.gd/python_ms

4) Upload code
- Copy your PHP app to web root (replace demo files). Ensure config files are created:
  - config/site.php (from provided file)
  - config/app.php, config/paypal.php (do NOT commit secrets)
  - Put .env.production at project root and set permissions chmod 600 .env.production

5) Python microservice
- Create a virtualenv inside python_ms and install requirements:
  python3 -m venv venv
  source venv/bin/activate
  pip install -r requirements.txt
- Configure config/paypal_py.py with live client_id and client_secret (do not commit).
- Start service via systemd unit (edit to correct paths) then:
  sudo systemctl daemon-reload
  sudo systemctl enable --now gunicorn-file-ms.service

6) Nginx
- Put the provided nginx config in /etc/nginx/sites-available/ and symlink to sites-enabled.
- Test nginx configuration: sudo nginx -t
- Reload nginx: sudo systemctl reload nginx

7) SSL
- Use certbot to obtain certificates:
  sudo certbot --nginx -d file.match3onlinerewareds.gamer.gd --email your-email@example.com --agree-tos --non-interactive
- Confirm HTTPS is working.

8) Database
- Create MySQL database and user, run db/schema.sql to create tables.
- Update config/app.php (or .env.production) with DB credentials.

9) Webhook registration
- In PayPal dashboard (live app), register the webhook URL:
  https://file.match3onlinerewareds.gamer.gd/api/webhook.php
- Subscribe to PAYMENT.PAYOUTS-ITEM.* events and any others you need.
- Put returned webhook ID into config/paypal.php or .env.production (PAYPAL_WEBHOOK_ID).

10) Security
- Ensure config files and .env are not web-accessible (chmod 600, owner www-data).
- Set up firewall and fail2ban.
- Audit logs and enable monitoring.

11) Remove demo server
- Remove demo assets (assets/game.js, demo index) or replace them with the provided index redirect.
- Ensure no demo-only endpoints are reachable publicly.

12) Final tests
- Create a test user, fund account in sandbox or internal test mode (do not use live until fully tested).
- Test payout flow using sandbox credentials first, then switch to live and do small payouts.
- Test webhook event delivery and DB reconciliation.

If you'd like, I can:
- Generate the exact php-fpm socket path and tweak the nginx conf to match your PHP version.
- Convert webhook.php to update the payouts DB (I can do this now if you want).
- Provide a small script to harden file permissions and remove demo files automatically.