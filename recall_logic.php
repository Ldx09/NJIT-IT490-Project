<?php

$stmt->execute();
$recall_id = $connection->insert_id;

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

$stmt = $connection->prepare("SELECT id FROM vehicles ...");
