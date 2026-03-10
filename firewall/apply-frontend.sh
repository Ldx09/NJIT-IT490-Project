#!/bin/bash
set -e
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw reject 3306/tcp
ufw --force enable
echo "frontend done"
