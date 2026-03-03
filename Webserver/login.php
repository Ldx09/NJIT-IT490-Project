<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.html");
    exit(0);
}

$username = $_POST["username"] ?? "";
$password = $_POST["password"] ?? "";

// validation
if ($username == "" || $password == "") {
    echo "Username and Password required.";
    exit(0);
}

require_once("path.inc");
require_once("get_host_info.inc");
require_once("rabbitMQLib.inc");

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

$request = array();
$request["type"] = "login";
$request["username"] = $username;
$request["password"] = $password;

$response = $client->send_request($request);

if (is_array($response) && isset($response["status"]) && $response["status"] === "ok" && isset($response["session_key"])) {
    $_SESSION["username"] = $username;
    $_SESSION["session_key"] = $response["session_key"];
    header("Location: home.php");
    exit(0);
}

echo "Login Failed.";
exit(0);
?>
