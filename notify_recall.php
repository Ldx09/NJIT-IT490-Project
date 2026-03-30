<?php

require_once __DIR__ . '/notify_logger.php';
require_once __DIR__ . '/notify_mailer.php';
require_once __DIR__ . '/notify_sms.php';

$rabbitMQLib = __DIR__ . '/../Database/rabbitMQLib.inc';
if (file_exists($rabbitMQLib)) {
    require_once $rabbitMQLib;
}

function get_users_affected_by_recall(array $recall, bool $use_mock = true): array {

    if ($use_mock) {
        return [
            [
                'id'           => 1,
                'username'     => 'JohnDoe',
                'email'        => '@ldx9651@gmail.com',
                'phone'        => '+18882827892',
                'notify_email' => 1,
                'notify_sms'   => 0,
                'car_year'     => $recall['year']  ?? '2020',
                'car_make'     => $recall['make'],
                'car_model'    => $recall['model'],
                'car_color'    => 'Silver'
            ],
            [
                'id'           => 2,
                'username'     => 'JaneSmith',
                'email'        => 'ldx9651@gmail.com',
                'phone'        => '+18882827892',
                'notify_email' => 1,
                'notify_sms'   => 1,
                'car_year'     => $recall['year']  ?? '2019',
                'car_make'     => $recall['make'],
                'car_model'    => $recall['model'],
                'car_color'    => 'Blue'
            ]
        ];
    }

    try {
        $client   = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        $response = $client->send_request([
            'type'  => 'GET_AFFECTED_USERS',
            'make'  => $recall['make'],
            'model' => $recall['model']
        ]);

        if (!isset($response['status']) || $response['status'] !== 'ok') {
            notify_log("RabbitMQ error getting affected users: " . json_encode($response));
            return [];
        }

        return $response['users'] ?? [];

    } catch (Exception $e) {
        notify_log("RabbitMQ ERROR (get_users_affected_by_recall): " . $e->getMessage());
        return [];
    }
}

function notify_users_of_recall(array $recall, bool $use_mock = true): void {
    notify_log("=== Recall notification: {$recall['make']} {$recall['model']} [{$recall['nhtsa_id']}]");

    $users = get_users_affected_by_recall($recall, $use_mock);

    if (empty($users)) {
        notify_log("No matching users found — no notifications sent.");
        return;
    }

    notify_log("Notifying " . count($users) . " user(s)...");

    foreach ($users as $user) {
        $car = [
            'year'  => $user['car_year'],
            'make'  => $user['car_make'],
            'model' => $user['car_model'],
            'color' => $user['car_color']
        ];

        if (!empty($user['notify_email']) && !empty($user['email'])) {
            $subject = "Recall Alert: Your {$car['year']} {$car['make']} {$car['model']}";
            $body    = build_recall_email($user, $car, $recall);
            send_email($user['email'], $user['username'], $subject, $body);
        }

        if (!empty($user['notify_sms']) && !empty($user['phone'])) {
            $msg = build_recall_sms($car, $recall);
            send_sms($user['phone'], $msg);
        }
    }

    notify_log("=== Done: recall notification [{$recall['nhtsa_id']}]");
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "\n=== D6 Test: notify_recall.php (mock mode) ===\n\n";

    $mock_recall = [
        'nhtsa_id'    => 'NHTSA-TEST-001',
        'make'        => 'Toyota',
        'model'       => 'Camry',
        'year'        => '2020',
        'component'   => 'Airbag Inflator',
        'summary'     => 'The airbag inflator may rupture due to excessive internal pressure.',
        'recall_date' => date('Y-m-d')
    ];

    notify_users_of_recall($mock_recall, use_mock: true);
    echo "\nCheck logs/notifications.log for results.\n\n";
}
