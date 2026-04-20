#!/usr/bin/php
<?php


require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

// check command arguments
if ($argc < 4) {
    echo "Usage:\n";
    echo "  php deploy_processor.php deploy qa v1 bundle_v1.tgz\n";
    echo "  php deploy_processor.php rollback qa v1\n";
    exit(1);
}

$action  = $argv[1]; // deploy or rollback
$target  = $argv[2]; // qa or prod
$version = $argv[3]; // version name
$bundle  = $argv[4] ?? ""; // bundle file for deploy

// validation
if ($action !== "deploy" && $action !== "rollback") {
    echo "Invalid action. Use deploy or rollback.\n";
    exit(1);
}

if ($target !== "qa" && $target !== "prod") {
    echo "Invalid target. Use qa or prod.\n";
    exit(1);
}

if ($action === "deploy" && $bundle === "") {
    echo "Bundle file required for deploy.\n";
    exit(1);
}

// connect to RabbitMQ
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// build request
$request = array();
$request["type"] = "deploy_command";
$request["action"] = $action;
$request["target"] = $target;
$request["version"] = $version;
$request["bundle"] = $bundle;
$request["time"] = date("Y-m-d H:i:s");

// send request
echo "Sending $action request to $target...\n";
$response = $client->send_request($request);

// show response from agent
if (is_array($response) && isset($response["status"])) {
    echo "Response status: " . $response["status"] . "\n";

    if (isset($response["message"])) {
        echo "Message: " . $response["message"] . "\n";
    }
} else {
    echo "No valid response from agent.\n";
}




echo "Done.\n";
?>
