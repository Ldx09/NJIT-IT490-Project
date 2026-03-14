<?php
// ============================================================
//  D6 — Recall Alert
//
//  TWO MODES:
//  1. STANDALONE (mock data, no DB needed):
//       php notify_recall.php
//
//  2. INTEGRATED (real DB, called from mq_consumer.php):
//       require_once 'notify_recall.php';
//       notify_users_of_recall($recall_array, use_mock: false);
// ============================================================

require_once __DIR__ . '/notify_logger.php';
require_once __DIR__ . '/notify_mailer.php';
require_once __DIR__ . '/notify_sms.php';

// ------------------------------------------------------------
//  Get all users who own a vehicle matching this recall
// ------------------------------------------------------------
function get_users_affected_by_recall(array $recall, bool $use_mock = true): array {

    // ---- MOCK MODE (no DB needed) --------------------------
    if ($use_mock) {
        return [
            [
                'id'           => 1,
                'username'     => 'JohnDoe',
                'email'        => 'test@example.com',  // ← change to YOUR email to test
                'phone'        => '+12015551234',
                'notify_email' => 1,
                'notify_sms'   => 0,
                'car'          => [
                    'year'  => $recall['year']  ?? '2020',
                    'make'  => $recall['make'],
                    'model' => $recall['model'],
                    'color' => 'Silver'
                ]
            ],
            [
                'id'           => 2,
                'username'     => 'JaneSmith',
                'email'        => 'test2@example.com',
                'phone'        => '+19735559876',
                'notify_email' => 1,
                'notify_sms'   => 1,
                'car'          => [
                    'year'  => $recall['year']  ?? '2019',
                    'make'  => $recall['make'],
                    'model' => $recall['model'],
                    'color' => 'Blue'
                ]
            ]
        ];
    }

    // ---- REAL DB MODE (used after team integration) --------
    try {
        // Adjust path to match where your team's db.php lives
        require_once __DIR__ . '/../Database/db.php';
        $pdo = get_db_connection();

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.username,
                u.email,
                u.phone,
                u.notify_email,
                u.notify_sms,
                c.year  AS car_year,
                c.make  AS car_make,
                c.model AS car_model,
                c.color AS car_color
            FROM users u
            JOIN cars c ON c.user_id = u.id
            WHERE
                LOWER(c.make)  = LOWER(:make)
            AND LOWER(c.model) = LOWER(:model)
            AND (u.notify_email = 1 OR u.notify_sms = 1)
        ");
        $stmt->execute([':make' => $recall['make'], ':model' => $recall['model']]);

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['car'] = [
                'year'  => $row['car_year'],
                'make'  => $row['car_make'],
                'model' => $row['car_model'],
                'color' => $row['car_color']
            ];
            unset($row['car_year'], $row['car_make'], $row['car_model'], $row['car_color']);
            $rows[] = $row;
        }
        return $rows;

    } catch (Exception $e) {
        notify_log("DB ERROR (get_users_affected_by_recall): " . $e->getMessage());
        return [];
    }
}

// ------------------------------------------------------------
//  Main function — notify everyone affected by a recall
// ------------------------------------------------------------
function notify_users_of_recall(array $recall, bool $use_mock = true): void {
    notify_log("=== Recall notification: {$recall['make']} {$recall['model']} [{$recall['nhtsa_id']}]");

    $users = get_users_affected_by_recall($recall, $use_mock);

    if (empty($users)) {
        notify_log("No matching users found — no notifications sent.");
        return;
    }

    notify_log("Notifying " . count($users) . " user(s)...");

    foreach ($users as $user) {
        $car = $user['car'];

        // Email
        if (!empty($user['notify_email']) && !empty($user['email'])) {
            $subject = "Recall Alert: Your {$car['year']} {$car['make']} {$car['model']}";
            $body    = build_recall_email($user, $car, $recall);
            send_email($user['email'], $user['username'], $subject, $body);
        }

        // SMS
        if (!empty($user['notify_sms']) && !empty($user['phone'])) {
            $msg = build_recall_sms($car, $recall);
            send_sms($user['phone'], $msg);
        }
    }

    notify_log("=== Done: recall notification for [{$recall['nhtsa_id']}]");
}

// ------------------------------------------------------------
//  STANDALONE TEST — run directly: php notify_recall.php
// ------------------------------------------------------------
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "\n=== D6 Test: notify_recall.php (mock mode) ===\n\n";

    $mock_recall = [
        'nhtsa_id'    => 'NHTSA-TEST-001',
        'make'        => 'Toyota',
        'model'       => 'Camry',
        'year'        => '2020',
        'component'   => 'Airbag Inflator',
        'summary'     => 'The airbag inflator may rupture due to excessive internal pressure, posing a risk of injury.',
        'recall_date' => date('Y-m-d')
    ];

    notify_users_of_recall($mock_recall, use_mock: true);

    echo "\nCheck logs/notifications.log to see results.\n\n";
}