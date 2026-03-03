<?php

require_once(__DIR__ . '/path.inc');
require_once(__DIR__ . '/get_host_info.inc');
require_once(__DIR__ . '/rabbitMQLib.inc');

require_once(__DIR__ . '/auth_logic.php'); 

function requestHandler($request)
{
    echo "... Incoming request\n";
    var_dump($request);

    // check for valid request
    if (!is_array($request) || !isset($request['type'])) {
        return ["status" => "error"];
    }

    $type = $request['type'];

    if ($type === "register") {
        $username = $request['username'] ?? '';
        $password = $request['password'] ?? '';
        return auth_register($username, $password);
    }

    if ($type === "login") {
        $username = $request['username'] ?? '';
        $password = $request['password'] ?? '';
        return auth_login($username, $password);
    }

    if ($type === "validate_session") {
        $session_key = $request['session_key'] ?? '';
        return auth_validate_session($session_key);
    }

    return ["status" => "error"];
}

// Start listener
$iniFile   = __DIR__ . "/testRabbitMQ.ini";
$serverKey = "testServer"; 

$server = new rabbitMQServer($iniFile, $serverKey);

echo "Auth listener running...\n";
echo "waiting for requests...\n";

$server->process_requests('requestHandler');
