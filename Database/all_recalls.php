<?php
session_start();

if (!isset($_SESSION["username"]) || !isset($_SESSION["session_key"])) {
    header("Location: index.html");
    exit(0);
}

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// validate session
$request = array();
$request['type'] = "validate_session";
$request['session_key'] = $_SESSION["session_key"];

$response = $client->send_request($request);

if (!is_array($response) || !isset($response["status"]) || $response["status"] !== "ok") {
    session_destroy();
    header("Location: index.html");
    exit(0);
}

// get all recalls
$request = array();
$request['type'] = "getAllRecalls";

$response = $client->send_request($request);

$recalls = array();

if (is_array($response) && isset($response["status"]) && $response["status"] === "ok" && isset($response["recalls"])) {
    $recalls = $response["recalls"];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>All Recalls</title>
</head>
<body>

<h2>All Recalls</h2>
<p>Welcome <?php echo $_SESSION["username"]; ?></p>

<?php if (count($recalls) == 0) { ?>
    <p>No recalls found in system.</p>
<?php } else { ?>
    <table border="1" cellpadding="8">
        <tr>
            <th>NHTSA ID</th>
            <th>Make</th>
            <th>Model</th>
            <th>Year</th>
            <th>Component</th>
            <th>Summary</th>
            <th>Recall Date</th>
        </tr>

        <?php foreach ($recalls as $r) { ?>
        <tr>
            <td><?php echo $r["nhtsa_id"]; ?></td>
            <td><?php echo $r["make"]; ?></td>
            <td><?php echo $r["model"]; ?></td>
            <td><?php echo $r["year"]; ?></td>
            <td><?php echo $r["component"]; ?></td>
            <td><?php echo $r["summary"]; ?></td>
            <td><?php echo $r["recall_date"]; ?></td>
        </tr>
        <?php } ?>
    </table>
<?php } ?>

<br><br>
<a href="home.php">Back to Home</a>
<br><br>
<a href="logout.php">Logout</a>

</body>
</html>