<?php

$stmt->execute();
$recall_id = $connection->insert_id;

// ← ADD THIS BLOCK RIGHT HERE (new recall inserted, notify users)
require_once __DIR__ . '/../D6_Notifications/notify_recall.php';
notify_users_of_recall([
    'nhtsa_id'    => $nhtsa_id,
    'make'        => $make,
    'model'       => $model,
    'year'        => $year,
    'component'   => $component,
    'summary'     => $summary,
    'recall_date' => $recall_date
], use_mock: false);
// ← END OF ADDED BLOCK

// THEIR EXISTING CODE continues below...
$stmt = $connection->prepare("SELECT id FROM vehicles ...");