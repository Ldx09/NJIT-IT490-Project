<?php

require_once __DIR__ . '/notify_logger.php';
require_once __DIR__ . '/notify_mailer.php';
require_once __DIR__ . '/notify_sms.php';

$rabbitMQLib = __DIR__ . '/../Database/rabbitMQLib.inc';
if (file_exists($rabbitMQLib)) {
    require_once $rabbitMQLib;
}

function get_upcoming_appointments(bool $use_mock = true): array {

    if ($use_mock) {
        $tomorrow = (new DateTime())->modify('+23 hours')->format('Y-m-d H:i:s');
        return [
            [
                'id'            => 101,
                'user_id'       => 1,
                'username'      => 'JohnDoe',
                'email'         => 'ldx9651@gmail.com',  // ← put YOUR email to test
                'phone'         => '+18882827892',
                'notify_email'  => 1,
                'notify_sms'    => 0,
                'shop_name'     => 'Toyota of Newark',
                'shop_address'  => '123 Main St, Newark, NJ 07102',
                'appt_type'     => 'in_person',
                'appt_datetime' => $tomorrow,
                'meeting_link'  => null,
                'reminder_sent' => 0
            ],
            [
                'id'            => 102,
                'user_id'       => 2,
                'username'      => 'JaneSmith',
                'email'         => 'ldx9651@gmail.com',
                'phone'         => '+18882827892',
                'notify_email'  => 1,
                'notify_sms'    => 1,
                'shop_name'     => 'AutoCare Virtual',
                'shop_address'  => 'Online',
                'appt_type'     => 'virtual',
                'appt_datetime' => $tomorrow,
                'meeting_link'  => 'https://meet.google.com/abc-defg-hij',
                'reminder_sent' => 0
            ]
        ];
    }

    try {
        $client   = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        $response = $client->send_request([
            'type' => 'GET_UPCOMING_APPOINTMENTS'
        ]);

        if (!isset($response['status']) || $response['status'] !== 'ok') {
            notify_log("RabbitMQ error getting appointments: " . json_encode($response));
            return [];
        }

        return $response['appointments'] ?? [];

    } catch (Exception $e) {
        notify_log("RabbitMQ ERROR (get_upcoming_appointments): " . $e->getMessage());
        return [];
    }
}

function mark_reminder_sent(int $appt_id, bool $use_mock = true): void {
    if ($use_mock) {
        notify_log("MOCK: marked appointment #{$appt_id} reminder_sent = 1");
        return;
    }

    try {
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        $client->send_request([
            'type'    => 'MARK_REMINDER_SENT',
            'appt_id' => $appt_id
        ]);
    } catch (Exception $e) {
        notify_log("RabbitMQ ERROR (mark_reminder_sent): " . $e->getMessage());
    }
}

function send_appointment_reminders(bool $use_mock = true): void {
    notify_log("=== Appointment reminder job started");

    $appointments = get_upcoming_appointments($use_mock);

    if (empty($appointments)) {
        notify_log("No appointments due for reminders.");
        notify_log("=== Done");
        return;
    }

    notify_log("Found " . count($appointments) . " appointment(s) to remind.");

    foreach ($appointments as $appt) {
        $user = [
            'id'       => $appt['user_id'],
            'username' => $appt['username'],
            'email'    => $appt['email'],
            'phone'    => $appt['phone']
        ];

        $sent = false;

        
        if (!empty($appt['notify_email']) && !empty($appt['email'])) {
            $subject = "Reminder: Your appointment at {$appt['shop_name']} is tomorrow";
            $body    = build_reminder_email($user, $appt);
            $sent    = send_email($appt['email'], $appt['username'], $subject, $body);
        }

        
        if (!empty($appt['notify_sms']) && !empty($appt['phone'])) {
            $msg  = build_reminder_sms($appt);
            $sent = send_sms($appt['phone'], $msg) || $sent;
        }

        if ($sent) {
            mark_reminder_sent((int)$appt['id'], $use_mock);
        }
    }

    notify_log("=== Appointment reminder job complete");
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "\n=== D6 Test: notify_reminder.php (mock mode) ===\n\n";
    send_appointment_reminders(use_mock: true);
    echo "\nCheck logs/notifications.log for results.\n\n";
}
