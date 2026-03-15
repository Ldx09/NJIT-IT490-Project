#!/bin/bash
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw reject 3306/tcp
ufw --force enable
echo "dmz done"
