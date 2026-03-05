<?php
session_start();

require_once("path.inc");
require_once("get_host_info.inc");
require_once("rabbitMQLib.inc");


if (isset($_SESSION["session_key"])) {

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $request = array();
    $request["type"] = "logout";
    $request["session_key"] = $_SESSION["session_key"];

    $client->send_request($request);
}

// destroying PHP session also
session_destroy();

header("Location: index.html");

exit();

?>
