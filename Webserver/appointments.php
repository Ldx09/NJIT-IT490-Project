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

$appointmentQueue = new rabbitMQClient("testRabbitMQ.ini", "testServer");

$appointmentFeedback = $_GET["msg"] ?? "";
$listLoadError = "";
$upcomingRecallAppointments = [];

// Add appointment
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["appointment_at"], $_POST["title"])) {
    $titleTrimmed = trim((string) $_POST["title"]);

    if ($titleTrimmed === '') {
        header("Location: appointments.php?msg=Title+is+required.");
        exit(0);
    }

    $addPayload = array(
        'type' => 'ADD_APPOINTMENT',
        'session_key' => $session_key,
        'appointment_at' => $_POST["appointment_at"],
        'title' => $titleTrimmed,
        'appointment_type' => $_POST["appointment_type"] ?? 'virtual',
        'location_or_link' => isset($_POST["location_or_link"]) ? trim((string) $_POST["location_or_link"]) : ''
    );

    $addResult = $appointmentQueue->send_request($addPayload);

    if (is_array($addResult) && isset($addResult["status"]) && $addResult["status"] === "ok") {
        header("Location: appointments.php?msg=Appointment+added.");
        exit(0);
    } else {
        header("Location: appointments.php?msg=Could+not+add+appointment.");
        exit(0);
    }
}

// Get appointments
$listPayload = array(
    'type' => 'GET_APPOINTMENTS',
    'session_key' => $session_key
);

$listResult = $appointmentQueue->send_request($listPayload);

if (!is_array($listResult)) {
    $listLoadError = "Couldn't load appointments. Try again.";
} elseif (isset($listResult["appointments"])) {
    $upcomingRecallAppointments = $listResult["appointments"];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>My appointments | RecallShield</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="container">
    <div class="card">
        <h2>My Appointments</h2>
        <p>Schedule recall-related visits or virtual check-ins.</p>

        <div class="nav-links">
            <a class="btn" href="home.php">Back to Home</a>
            <a class="btn" href="help_me_fix.php">Help Me Fix It</a>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="card">
        <form method="POST">
            <label>Date & Time</label><br><br>
            <input type="datetime-local" name="appointment_at" required><br><br>

            <label>Title</label><br><br>
            <input type="text" name="title" placeholder="e.g. Recall fix at dealer" required><br><br>

            <label>Type</label><br><br>
            <select name="appointment_type">
                <option value="virtual">Virtual</option>
                <option value="in_person">In person</option>
            </select><br><br>

            <label>Location or link</label><br><br>
            <input type="text" name="location_or_link" placeholder="Address or meeting URL"><br><br>

            <input class="btn" type="submit" value="Add Appointment">
        </form>

        <?php if ($appointmentFeedback !== '') { ?>
            <p class="warning"><?php echo htmlspecialchars($appointmentFeedback); ?></p>
        <?php } ?>
    </div>

    <div class="card">
        <h3>Upcoming</h3>

        <?php if ($listLoadError !== '') { ?>
            <p class="danger"><?php echo htmlspecialchars($listLoadError); ?></p>
        <?php } ?>

        <?php if (empty($upcomingRecallAppointments)) { ?>
            <p class="empty-message">No upcoming appointments.</p>
        <?php } else { ?>
            <table>
                <tr>
                    <th>Date & Time</th>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Location / Link</th>
                </tr>
                <?php foreach ($upcomingRecallAppointments as $apt) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($apt['appointment_at']); ?></td>
                        <td><?php echo htmlspecialchars($apt['title']); ?></td>
                        <td><?php echo htmlspecialchars($apt['type']); ?></td>
                        <td><?php echo htmlspecialchars($apt['location_or_link'] ?? ''); ?></td>
                    </tr>
                <?php } ?>
            </table>
        <?php } ?>
    </div>
</div>

</body>
</html>
