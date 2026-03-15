#!/usr/bin/php
<?php
 // Listens for authentication requests

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

require_once('auth_logic.php'); 
require_once('recall_logic.php');
require_once('appointment_logic.php');


function requestHandler($request)
{
    echo "... Incoming request\n";
    var_dump($request);

    // check for valid request
    if (!is_array($request) || !isset($request['type'])) {
        return ["status" => "error"];
    }

    $type = $request['type'];

    if ($type=== "register") {
        $username= $request['username']?? '';
        $password= $request['password']?? '';
        $vin= $request['vin']?? '';
        $carMake= $request['car_make']?? '';
        $model= $request['model']?? '';
        $trim= $request['trim']?? '';
        $color= $request['color']?? '';
        $year= $request['year']?? '';
        


    return auth_register($username, $password, 
    $vin, $carMake, $model, $trim, $color, $year);
    }

    if ($type=== "login") {
        $username= $request['username'] ?? '';
        $password= $request['password'] ?? '';
        return auth_login($username, $password);
    }

    if ($type=== "validate_session") {
        $session_key = $request['session_key'] ?? '';
        return auth_validate_session($session_key);
    }

    if ($type=== "logout") {
    $session_key= $request['session_key'] ?? '';
    return auth_logout($session_key);
}

if ($type==="INGEST_RECALL_BATCH") {
    $batch= $request['batch']?? [];
    return ingestRecall($batch);
}

if ($type === "getUserRecalls") {
    $username = $request['username'] ?? '';
    return getUserRecalls($username);
}

if ($type === "getAllRecalls") {
    return getAllRecalls();
}

if ($type === "getUserVehicles") {
    $username = $request['username'] ?? '';
    return getUserVehicles($username);
}

if ($type === "markRecallComplete") {
    $username = $request['username'] ?? '';
    $vehicle_recall_id = $request['vehicle_recall_id'] ?? '';
    return markRecallComplete($username, $vehicle_recall_id);
}

if ($type === "ADD_APPOINTMENT") {
        $session_key = $request['session_key'] ?? '';
        $user_id = get_user_id_by_session_key($session_key);
        if ($user_id === null) {
            return ["status" => "error"];
        }
        $appointment_at = $request['appointment_at'] ?? '';
        $title = $request['title'] ?? '';
        $appointment_type = $request['appointment_type'] ?? 'virtual';
        $location_or_link = $request['location_or_link'] ?? '';
        return appointment_create($user_id, $appointment_at, $title, $appointment_type, $location_or_link);
    }

  if ($type === "GET_APPOINTMENTS") {
        $session_key = $request['session_key'] ?? '';
        $user_id = get_user_id_by_session_key($session_key);
        if ($user_id === null) {
            return ["status" => "ok", "appointments" => []];
        }
        return appointment_list($user_id);
    }


    return ["status" => "error"];


}



// Start listener
$iniFile= "testRabbitMQ.ini";
$serverKey= "testServer"; 

$server = new rabbitMQServer($iniFile, $serverKey);

echo "Auth listener running...\n";
echo "From INI: $iniFile\n";
echo "From Server Key: $serverKey\n";
echo "waiting for requests...\n";

$server->process_requests('requestHandler');
