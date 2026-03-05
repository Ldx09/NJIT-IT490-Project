#!/usr/bin/php
<?php
 // Listens for authentication requests

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

require_once('auth_logic.php');

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

    if ($type === "logout") {
    $session_key = $request['session_key'] ?? '';
    return auth_logout($session_key);
}


    return ["status" => "error"];
}

// Start listener
$iniFile   = "testRabbitMQ.ini";
$serverKey = "testServer";

$server = new rabbitMQServer($iniFile, $serverKey);

echo "Auth listener running...\n";
echo "From INI: $iniFile\n";
echo "From Server Key: $serverKey\n";
echo "waiting for requests...\n";

$server->process_requests('requestHandler');
