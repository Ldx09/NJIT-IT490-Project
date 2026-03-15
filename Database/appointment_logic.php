<?php
require_once(__DIR__ . '/auth_logic.php');

/**
 * vehicleRecall.appointments stores recall/dealer visits.
 * Title capped at 255 (varchar) - we had truncation in prod when users pasted from dealer emails.
 */
function add_recall_visit($uid, $at, $title, $kind, $loc)
{
    if ($uid <= 0 || $at === '') return ["status" => "fail", "reason" => "bad_input"];
    if (strlen($title) > 255) {
        error_log('RecallShield add_recall_visit: title overflow uid=' . $uid);
        return ["status" => "error"];
    }
    if ($kind !== 'virtual' && $kind !== 'in_person') $kind = 'virtual';

    $ts = strtotime($at);
    if ($ts !== false && $ts < time()) return ["status" => "fail", "reason" => "past_date"];
    if ($ts !== false && $ts > strtotime('+2 years')) return ["status" => "fail", "reason" => "too_far"];

    if (strlen($loc) > 500) $loc = substr($loc, 0, 500);
    if ($kind === 'in_person' && strlen(trim($loc)) < 3) {
        return ["status" => "fail", "reason" => "in_person_needs_location"];
    }
    if ($kind === 'in_person' && (strpos($loc, '://') !== false || strpos($loc, 'http') === 0)) {
        $kind = 'virtual';
        error_log('RecallShield add_recall_visit: in_person had URL, switched to virtual uid=' . $uid);
    }

    $dbh = connectDB();
    if ($dbh === null) {
        error_log('RecallShield add_recall_visit: no db connection');
        return ["status" => "error"];
    }
    $insStmt = $dbh->prepare("INSERT INTO appointments (user_id, appointment_at, title, type, location_or_link, status) VALUES (?, ?, ?, ?, ?, 'scheduled')");
    if (!$insStmt) {
        error_log('RecallShield add_recall_visit: prepare failed');
        return ["status" => "error"];
    }
    $insStmt->bind_param("issss", $uid, $at, $title, $kind, $loc);
    $ok = $insStmt->execute();
    $insertId = $ok ? (int)$dbh->insert_id : 0;
    $insStmt->close();
    if (!$ok) return ["status" => "error"];
    return ["status" => "ok", "id" => $insertId];
}

/**
 * Only scheduled + future so past recall visits don't show. If prepare fails (table missing / perms)
 * we return ok+empty so the schedule page doesn't throw - check logs for the real cause.
 */
function fetch_scheduled_recall_visits($uid)
{
    $uid = (int) $uid;
    if ($uid <= 0) return ["status" => "ok", "appointments" => []];

    $dbh = connectDB();
    if ($dbh === null) {
        error_log('RecallShield fetch_scheduled_recall_visits: auth_db null');
        return ["status" => "error", "appointments" => []];
    }
    $sql = "SELECT id, appointment_at, title, type, location_or_link, status FROM appointments WHERE user_id = ? AND status = 'scheduled' AND appointment_at >= NOW() ORDER BY appointment_at ASC LIMIT 5";
    $query = $dbh->prepare($sql);
    if (!$query) return ["status" => "ok", "appointments" => []];
    $query->bind_param("i", $uid);
    $query->execute();
    $resultSet = $query->get_result();
    $rows = [];
    while ($r = $resultSet->fetch_assoc()) $rows[] = $r;
    $query->close();
    return ["status" => "ok", "appointments" => $rows];
}

function appointment_create($user_id, $appointment_at, $title, $type, $location_or_link)
{
    $title = trim((string) $title);
    $location_or_link = trim((string) $location_or_link);
    if ($type !== 'virtual' && $type !== 'in_person') $type = 'virtual';
    return add_recall_visit((int) $user_id, $appointment_at, $title, $type, $location_or_link);
}

function appointment_list($user_id)
{
    $user_id = (int) $user_id;
    return fetch_scheduled_recall_visits($user_id);
}
