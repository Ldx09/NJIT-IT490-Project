<?php
session_start();

// session check
if (!isset($_SESSION["username"]) || !isset($_SESSION["session_key"])) {
    header("Location: index.html");
    exit(0);
}

// RabbitMQ includes 
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

$request = array();
$request['type'] = "validate_session";
$request['session_key'] = $_SESSION["session_key"];

$response = $client->send_request($request);

// If session invalid then kick out
if (!is_array($response) || !isset($response["status"]) || $response["status"] !== "ok") {
    session_destroy();
    header("Location: index.html");
    exit(0);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Home Page</title>
</head>
<body>

<h2>It works, Welcome <?php echo $_SESSION["username"]; ?></h2>

</body>
</html>
