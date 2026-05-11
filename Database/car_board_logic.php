<?php
// car board logic via listener
require_once(__DIR__ . '/auth_logic.php');

// optional car field rules
function recall_whip_ok_for_recall_board($whip)
{
    if ($whip === '') {
        return true;
    }
    if (strlen($whip) < 3) {
        return false;
    }
    $up = strtoupper($whip);
    if (strlen($up) === 17 && ctype_alnum($up)) {
        if (strpbrk($up, 'IOQ') !== false) {
            return false;
        }
        return true;
    }
    return preg_match('/[0-9]/', $whip) === 1;
}

// insert new thread row
function car_ins_thread($uid, $title, $car_info, $body)
{
    $uid = (int) $uid;
    $topic = trim(preg_replace('/\s+/', ' ', strip_tags((string) $title)));
    $whip = trim(strip_tags((string) $car_info));
    $opening = trim(strip_tags((string) $body));

    if ($uid < 1) {
        return ["status" => "fail", "reason" => "bad_user"];
    }
    if ($topic === '' || $opening === '') {
        return ["status" => "fail", "reason" => "need_body"];
    }
    if (!recall_whip_ok_for_recall_board($whip)) {
        return ["status" => "fail", "reason" => "car_info_needs_number"];
    }

    if (strlen($topic) > 255) {
        $topic = substr($topic, 0, 255);
    }
    if (strlen($whip) > 200) {
        $whip = substr($whip, 0, 200);
    }
    if (strlen($opening) > 18000) {
        $opening = substr($opening, 0, 18000);
    }

    $conn = auth_db();
    if ($conn === null) {
        return ["status" => "error"];
    }

    $stmt = $conn->prepare("INSERT INTO car_threads (user_id, title, car_info, body) VALUES (?, ?, ?, ?)");
    if ($stmt === false) {
        return ["status" => "error"];
    }

    $stmt->bind_param("isss", $uid, $topic, $whip, $opening);
    $ok = $stmt->execute();
    if (!$ok) {
        $stmt->close();
        return ["status" => "error"];
    }
    $newId = (int) $conn->insert_id;
    $stmt->close();
    return ["status" => "ok", "thread_id" => $newId];
}

// newest threads list
function car_feed($lim)
{
    $lim = (int) $lim;
    if ($lim < 1) {
        $lim = 8;
    }
    if ($lim > 50) {
        $lim = 50;
    }

    $conn = auth_db();
    if ($conn === null) {
        return ["status" => "error", "threads" => []];
    }

    $sql = "SELECT t.id, t.user_id, t.title, t.car_info, t.created_at, u.username FROM car_threads t"
        . " INNER JOIN users u ON u.id = t.user_id ORDER BY t.created_at DESC LIMIT " . $lim;
    $res = $conn->query($sql);
    if ($res === false) {
        return ["status" => "error", "threads" => []];
    }

    $threads = [];
    while ($row = $res->fetch_assoc()) {
        $threads[] = $row;
    }
    return ["status" => "ok", "threads" => $threads];
}

// thread plus replies
function car_fetch($tid)
{
    $tid = (int) $tid;
    if ($tid < 1) {
        return ["status" => "fail", "reason" => "bad_thread"];
    }

    $conn = auth_db();
    if ($conn === null) {
        return ["status" => "error"];
    }

    $stmt = $conn->prepare("SELECT t.id, t.user_id, t.title, t.car_info, t.body, t.created_at, u.username AS author FROM car_threads t INNER JOIN users u ON u.id = t.user_id WHERE t.id = ?");
    if ($stmt === false) {
        return ["status" => "error"];
    }
    $stmt->bind_param("i", $tid);
    $stmt->execute();
    $rs = $stmt->get_result();
    $thread = $rs ? $rs->fetch_assoc() : null;
    $stmt->close();

    if (!$thread) {
        return ["status" => "fail", "reason" => "gone"];
    }

    $replies = [];
    $stmt2 = $conn->prepare("SELECT r.id, r.user_id, r.body, r.created_at, u.username AS author FROM car_replies r INNER JOIN users u ON u.id = r.user_id WHERE r.thread_id = ? ORDER BY r.id ASC");
    if ($stmt2 !== false) {
        $stmt2->bind_param("i", $tid);
        $stmt2->execute();
        $rs2 = $stmt2->get_result();
        if ($rs2) {
            while ($r = $rs2->fetch_assoc()) {
                $replies[] = $r;
            }
        }
        $stmt2->close();
    }

    return ["status" => "ok", "thread" => $thread, "replies" => $replies];
}

// insert reply row
function car_reply_ins($uid, $tid, $msg)
{
    $uid = (int) $uid;
    $tid = (int) $tid;
    $replyTxt = trim(strip_tags((string) $msg));

    if ($uid < 1 || $tid < 1 || $replyTxt === '') {
        return ["status" => "fail", "reason" => "bad_input"];
    }
    if (strlen($replyTxt) > 8000) {
        $replyTxt = substr($replyTxt, 0, 8000);
    }

    $conn = auth_db();
    if ($conn === null) {
        return ["status" => "error"];
    }

    $chk = $conn->prepare("SELECT id FROM car_threads WHERE id = ? LIMIT 1");
    if ($chk === false) {
        return ["status" => "error"];
    }
    $chk->bind_param("i", $tid);
    $chk->execute();
    $row = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$row) {
        return ["status" => "fail", "reason" => "thread_missing"];
    }

    $ins = $conn->prepare("INSERT INTO car_replies (thread_id, user_id, body) VALUES (?, ?, ?)");
    if ($ins === false) {
        return ["status" => "error"];
    }
    $ins->bind_param("iis", $tid, $uid, $replyTxt);
    if (!$ins->execute()) {
        $ins->close();
        return ["status" => "error"];
    }
    $replyId = (int) $conn->insert_id;
    $ins->close();
    return ["status" => "ok", "reply_id" => $replyId];
}
