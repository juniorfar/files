#!/bin/bash
# Run as root or with sudo
ufw allow OpenSSH
ufw allow 'Nginx Full'   # allow 80 & 443
# Deny 5000 from all (if gunicorn accidentally binds external)
ufw deny 5000
# Enable ufw
ufw --force enable
ufw status verbose