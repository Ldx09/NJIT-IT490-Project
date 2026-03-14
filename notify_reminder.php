<?php
// ============================================================
//  D6 — Appointment Reminder (Cron Job)
//
//  TWO MODES:
//  1. STANDALONE TEST (mock data, no DB):
//       php notify_reminder.php
//
//  2. CRON (runs automatically every hour on Ubuntu):
//       crontab -e
//       Add: 0 * * * * /usr/bin/php /var/www/html/D6_Notifications/notify_reminder.php
// ============================================================

require_once __DIR__ . '/notify_logger.php';
require_once __DIR__ . '/notify_mailer.php';
require_once __DIR__ . '/notify_sms.php';

// ------------------------------------------------------------
//  Get appointments happening in the next 24 hours
// ------------------------------------------------------------
function get_upcoming_appointments(bool $use_mock = true): array {

    // ---- MOCK MODE -----------------------------------------
    if ($use_mock) {
        $tomorrow = (new DateTime())->modify('+23 hours')->format('Y-m-d H:i:s');
        return [
            [
                'id'            => 101,
                'user_id'       => 1,
                'username'      => 'JohnDoe',
                'email'         => 'test@example.com',  // ← change to YOUR email to test
                'phone'         => '+12015551234',
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
                'email'         => 'test2@example.com',
                'phone'         => '+19735559876',
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

    // ---- REAL DB MODE --------------------------------------
    try {
        require_once __DIR__ . '/../Database/db.php';
        $pdo = get_db_connection();

        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.user_id,
                u.username,
                u.email,
                u.phone,
                u.notify_email,
                u.notify_sms,
                a.shop_name,
                a.shop_address,
                a.appt_type,
                a.appt_datetime,
                a.meeting_link,
                a.reminder_sent
            FROM appointments a
            JOIN users u ON u.id = a.user_id
            WHERE
                a.status         = 'upcoming'
            AND a.reminder_sent  = 0
            AND a.appt_datetime  BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
            AND (u.notify_email = 1 OR u.notify_sms = 1)
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        notify_log("DB ERROR (get_upcoming_appointments): " . $e->getMessage());
        return [];
    }
}

// ------------------------------------------------------------
//  Mark appointment reminder as sent (prevents duplicate sends)
// ------------------------------------------------------------
function mark_reminder_sent(int $appt_id, bool $use_mock = true): void {
    if ($use_mock) {
        notify_log("MOCK: marked appointment #{$appt_id} reminder_sent = 1");
        return;
    }
    try {
        require_once __DIR__ . '/../Database/db.php';
        $pdo  = get_db_connection();
        $stmt = $pdo->prepare("UPDATE appointments SET reminder_sent = 1 WHERE id = ?");
        $stmt->execute([$appt_id]);
    } catch (Exception $e) {
        notify_log("DB ERROR (mark_reminder_sent): " . $e->getMessage());
    }
}

// ------------------------------------------------------------
//  Main function — send all due reminders
// ------------------------------------------------------------
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

        // Email
        if (!empty($appt['notify_email']) && !empty($appt['email'])) {
            $subject = "Reminder: Your appointment at {$appt['shop_name']} is tomorrow";
            $body    = build_reminder_email($user, $appt);
            $sent    = send_email($appt['email'], $appt['username'], $subject, $body);
        }

        // SMS
        if (!empty($appt['notify_sms']) && !empty($appt['phone'])) {
            $msg  = build_reminder_sms($appt);
            $sent = send_sms($appt['phone'], $msg) || $sent;
        }

        // Mark sent so cron doesn't fire again
        if ($sent) {
            mark_reminder_sent((int)$appt['id'], $use_mock);
        }
    }

    notify_log("=== Appointment reminder job complete");
}

// ------------------------------------------------------------
//  STANDALONE TEST — run directly: php notify_reminder.php
// ------------------------------------------------------------
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "\n=== D6 Test: notify_reminder.php (mock mode) ===\n\n";
    send_appointment_reminders(use_mock: true);
    echo "\nCheck logs/notifications.log to see results.\n\n";
}