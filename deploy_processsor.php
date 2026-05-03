#!/usr/bin/php
<?php

// deployment controller, sends deploy or rollback command to prod and qa, do health check and take bundle version from bundle sh


require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');


$action  = $argv[1]; // deploy or rollback
$target  = $argv[2]; // qa or prod
$requestedVersion = $argv[3]; // version name from bundle sh


// configrations 

// database
$dbHost = "localhost";
$dbUser = "admin";
$dbPass = "123456";
$dbName = "it490Deploy";

// rabbitmq stuff for two different env
$rabbitConfigs = [
    "qa" => [
        "ini" => "qaRabbitMQ.ini",
        "serverKey" => "qaDeployServer",
    ],
    "prod" => [
        "ini" => "prodRabbitMQ.ini",
        "serverKey" => "prodDeployServer",
    ],
];


// files directory 
$bundleScript = __DIR__ . "/build_bundle.sh";
$healthcheckScript = __DIR__ . "/healthCheck.sh";
$logFile = __DIR__ . "/deployLog.txt";


// connect to mysql
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

// functions 

// write log for everthing
function writeLog(string $logFile, string $message): void
{
    $line = date("Y-m-d H:i:s") . " | " . $message . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
}


// send deploy request to qa or prod
function sendDeployRequest(string $target, string $action, string $version, string $bundleName, array $rabbitConfigs)
{
    $client = createRabbitClient($target, $rabbitConfigs);

    $request = [];
    $request["type"]    = "deploy_command";
    $request["action"]  = $action;
    $request["target"]  = $target;
    $request["version"] = $version;
    $request["bundle"]  = $bundleName;
    $request["time"]    = date("Y-m-d H:i:s");

    return $client->send_request($request);
}

// run bundle script and take version and bundle name from script
function runBundleScript(string $bundleScript): array
{
    if (!file_exists($bundleScript)) {
        return [
            "ok" => false,
            "message" => "Bundle script not found.",
            "version" => "",
            "bundle" => "",
            "output" => ""
        ];
    }
     // run and also take outputs
    exec("/bin/bash " . escapeshellarg($bundleScript) . " 2>&1", $output, $exitCode);
 
    // if script fails for some reason
    if ($exitCode !== 0) {
        return [
            "ok" => false,
            "message" => "Bundle script failed.",
            "version" => "",
            "bundle" => "",
            "output" => implode("\n", $output)
        ];
    }

    $version = "";
    $bundle = "";

// take version and bundle from script as output 
    foreach ($output as $line) {

        if ($line, "VERSION=") === 0 {
            $version = substr($line, 8);


        } elseif ($line, "BUNDLE=") === 0 {
            $bundle = ($line, 7);
        }
    }

// missing file
    if (!file_exists($bundle)) {
        return [
            "ok" => false,
            "message" => "Bundle file does not exist.",
            "version" => $version,
            "bundle" => $bundle,
            "output" => implode("\n", $output)
        ];
    }

    return [
        "ok" => true,
        "message" => "Bundle created.",
        "version" => $version,
        "bundle" => $bundle,
        "output" => implode("\n", $output)
    ];
}

// run health check on specific target 
function runHealthCheck(string $script, string $target)
{
    if (!file_exists($script)) {
        return [
            "ok" => false,
            "output" => "Health check script not found."
        ];
    }

    exec(
        "/bin/bash " . escapeshellarg($script) . " " . escapeshellarg($target) . " 2>&1",
        $output,
        $exitCode
    );

    return [
        "ok" => $exitCode === 0,
        "output" => implode("\n", $output)
    ];
}


