#!/bin/bash
if [ -z "$BACKEND_IP" ]; then
    BACKEND_IP="127.0.0.1"
fi
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow from "$BACKEND_IP" to any port 3306 proto tcp
ufw reject 3306/tcp
ufw allow from 10.246.134.0/24 to any port 5672 proto tcp
ufw --force enable
echo "database firewall on, mysql from $BACKEND_IP, rabbitmq from ZT 10.246.134.0/24"
