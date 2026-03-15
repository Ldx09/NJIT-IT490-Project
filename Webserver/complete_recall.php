<?php
session_start();

if (!isset($_SESSION["username"]) || !isset($_SESSION["session_key"])) {
    header("Location: index.html");
    exit(0);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: recall.php");
    exit(0);
}

$username = $_SESSION["username"];
$session_key = $_SESSION["session_key"];
$vehicle_recall_id = $_POST["vehicle_recall_id"] ?? "";

session_write_close();

if ($vehicle_recall_id == "") {
    header("Location: recall.php");
    exit(0);
}

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

$request = array();
$request["type"] = "markRecallComplete";
$request["username"] = $username;
$request["vehicle_recall_id"] = $vehicle_recall_id;

$response = $client->send_request($request);

// no matter what, go back to recall page
header("Location: recall.php");
exit(0);
?>