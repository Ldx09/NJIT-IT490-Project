<?php
session_start();

if (!isset($_SESSION["username"]) || !isset($_SESSION["session_key"])) {
    header("Location: index.html");
    exit(0);
}

$username = $_SESSION["username"];
$session_key = $_SESSION["session_key"];
session_write_close();

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

$request = array();
$request['type'] = "getUserVehicles";
$request['username'] = $username;

$response = $client->send_request($request);

$vehicles = array();

if (is_array($response) && isset($response["status"]) && $response["status"] === "ok" && isset($response["vehicles"])) {
    $vehicles = $response["vehicles"];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Garage</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="card">
        <h2>My Garage</h2>
        <p>Welcome <?php echo $username; ?></p>

        <div class="nav-links">
            <a class="btn" href="home.php">Back to Home</a>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="card">
        <?php if (count($vehicles) == 0) { ?>
            <p class="empty-message">No vehicles found in your garage.</p>
        <?php } else { ?>
            <table>
                <tr>
                    <th>VIN</th>
                    <th>Make</th>
                    <th>Model</th>
                    <th>Trim</th>
                    <th>Color</th>
                    <th>Year</th>
                </tr>

                <?php foreach ($vehicles as $v) { ?>
                    <tr>
                        <td><?php echo $v["vin"]; ?></td>
                        <td><?php echo $v["car_make"]; ?></td>
                        <td><?php echo $v["model"]; ?></td>
                        <td><?php echo $v["trim"]; ?></td>
                        <td><?php echo $v["color"]; ?></td>
                        <td><?php echo $v["year"]; ?></td>
                    </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </div>
</div>

</body>
</html>