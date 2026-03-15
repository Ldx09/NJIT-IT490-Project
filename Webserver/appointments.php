<?php
session_start();
// only logged-in users get recall/appointment schedule
if (!isset($_SESSION["username"]) || !isset($_SESSION["session_key"])) {
    header("Location: index.html");
    exit(0);
}
require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$appointmentQueue = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$sessionCheckPayload = array('type' => 'validate_session', 'session_key' => $_SESSION["session_key"]);
$sessionCheckResult = $appointmentQueue->send_request($sessionCheckPayload);
if (!is_array($sessionCheckResult) || !isset($sessionCheckResult["status"]) || $sessionCheckResult["status"] !== "ok") {
    session_destroy();
    header("Location: index.html");
    exit(0);
}

$appointmentFeedback = '';
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["appointment_at"], $_POST["title"])) {
    $titleTrimmed = trim((string) $_POST["title"]);
    if ($titleTrimmed === '') {
        $appointmentFeedback = 'Title is required.';
    } else {
        $addPayload = array(
            'type' => 'ADD_APPOINTMENT',
            'session_key' => $_SESSION["session_key"],
            'appointment_at' => $_POST["appointment_at"],
            'title' => $titleTrimmed,
            'appointment_type' => $_POST["appointment_type"] ?? 'virtual',
            'location_or_link' => isset($_POST["location_or_link"]) ? trim((string) $_POST["location_or_link"]) : ''
        );
        $addResult = $appointmentQueue->send_request($addPayload);
        if (is_array($addResult) && isset($addResult["status"]) && $addResult["status"] === "ok") {
            $appointmentFeedback = 'Appointment added.';
        } else {
            $appointmentFeedback = 'Could not add appointment. Try again or check the listener is running.';
            error_log('RecallShield ADD_APPOINTMENT failed for user ' . $_SESSION["username"]);
        }
    }
}

// fetch upcoming so we don't show past recall appointments
$listPayload = array('type' => 'GET_APPOINTMENTS', 'session_key' => $_SESSION["session_key"]);
$listResult = $appointmentQueue->send_request($listPayload);
$upcomingRecallAppointments = [];
$listLoadError = '';
if (!is_array($listResult)) {
    $listLoadError = 'Couldn\'t load appointments. Try again.';
} elseif (isset($listResult["appointments"])) {
    $upcomingRecallAppointments = $listResult["appointments"];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>My appointments | RecallShield</title>
    <style>
        body { font-family: sans-serif; margin: 1rem; }
        input, select, button { margin: 4px; padding: 6px; }
        table { border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        .msg { margin: 0.5rem 0; color: #333; }
        a { color: #06c; }
    </style>
</head>
<body>

<h2>My appointments</h2>
<p>Schedule recall-related visits or virtual check-ins.</p>

<form method="post">
    <label>Date & time</label><br>
    <input type="datetime-local" name="appointment_at" required><br>
    <label>Title</label><br>
    <input type="text" name="title" placeholder="e.g. Recall fix at dealer" size="40" required><br>
    <label>Type</label><br>
    <select name="appointment_type">
        <option value="virtual">Virtual</option>
        <option value="in_person">In person</option>
    </select><br>
    <label>Location or link</label><br>
    <input type="text" name="location_or_link" placeholder="Address or meeting URL" size="50"><br>
    <button type="submit">Add appointment</button>
</form>

<?php if ($appointmentFeedback !== '') { echo '<p class="msg">' . htmlspecialchars($appointmentFeedback) . '</p>'; } ?>

<h3>Upcoming</h3>
<?php if ($listLoadError !== '') { echo '<p class="msg">' . htmlspecialchars($listLoadError) . '</p>'; } ?>
<?php if (empty($upcomingRecallAppointments)) { ?>
    <p>No upcoming appointments.</p>
<?php } else { ?>
    <table>
        <tr><th>Date & time</th><th>Title</th><th>Type</th><th>Location / link</th></tr>
        <?php foreach ($upcomingRecallAppointments as $apt) {
            $at = htmlspecialchars($apt['appointment_at']);
            $title = htmlspecialchars($apt['title']);
            $type = htmlspecialchars($apt['type']);
            $loc = htmlspecialchars($apt['location_or_link'] ?? '');
            echo "<tr><td>$at</td><td>$title</td><td>$type</td><td>$loc</td></tr>";
        } ?>
    </table>
<?php } ?>

<p><a href="home.php">Back to home</a> | <a href="help_me_fix.php">Help me fix it</a> | <a href="logout.php">Logout</a></p>

</body>
</html>
