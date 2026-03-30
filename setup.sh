#!/bin/bash

echo ""
echo "=== D6 Notifications — Setup Script ==="
echo ""

echo "[1/4] Installing PHP and extensions..."
sudo apt update -y
sudo apt install -y php php-cli php-curl php-mbstring unzip curl

echo ""
echo "[2/4] Installing Composer..."
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version

echo ""
echo "[3/4] Installing PHP dependencies..."
composer install

echo ""
echo "[4/4] Setting up config file..."
if [ ! -f notify_config.php ]; then
    cp notify_config.example.php notify_config.php
    echo "Created notify_config.php — OPEN IT AND FILL IN YOUR CREDENTIALS"
else
    echo "notify_config.php already exists — skipping"
fi

mkdir -p logs

echo ""
echo "=== Setup complete! ==="
echo ""
echo "Next steps:"
echo "  1. Open notify_config.php and fill in your credentials"
echo "  2. Test with no credentials (log only mode):"
echo "       php notify_recall.php"
echo "       php notify_reminder.php"
echo "  3. Check logs/notifications.log for output"
echo ""
echo "To set up the cron job for appointment reminders:"
echo "  crontab -e"
echo "  Add this line:"
echo "  0 * * * * /usr/bin/php $(pwd)/notify_reminder.php >> /var/log/recall_reminders.log 2>&1"
echo ""
