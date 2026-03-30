<?php

require_once __DIR__ . '/notify_config.php';
require_once __DIR__ . '/notify_logger.php';
require_once __DIR__ . '/vendor/autoload.php';

use Twilio\Rest\Client;

function send_sms(string $to_phone, string $message): bool {
    if (empty($to_phone)) {
        notify_log("SMS SKIPPED — no phone number");
        return false;
    }

    if (!NOTIFY_SMS_ENABLED) {
        notify_log("SMS (disabled) → {$to_phone} | {$message}");
        return true;
    }

    try {
        $twilio = new Client(TWILIO_SID, TWILIO_TOKEN);
        $twilio->messages->create($to_phone, [
            'from' => TWILIO_FROM,
            'body' => $message
        ]);
        notify_log("SMS SENT → {$to_phone}");
        return true;
    } catch (\Exception $e) {
        notify_log("SMS FAILED → {$to_phone} | " . $e->getMessage());
        return false;
    }
}

function build_recall_sms(array $car, array $recall): string {
    $car_str   = "{$car['year']} {$car['make']} {$car['model']}";
    $component = $recall['component'];
    $id        = $recall['nhtsa_id'];
    return "RECALL ALERT: Your {$car_str} has an active recall ({$component}). "
        . "Log in to auth.com to find a repair shop. NHTSA: {$id}";
}

function build_reminder_sms(array $appt): string {
    $shop = $appt['shop_name'];
    $dt   = date('M j \a\t g:i A', strtotime($appt['appt_datetime']));
    $type = $appt['appt_type'] === 'virtual' ? 'virtual' : 'in-person';
    return "REMINDER: Your {$type} appt at {$shop} is tomorrow ({$dt}). "
        . "Details: auth.com/schedule.php";
}
