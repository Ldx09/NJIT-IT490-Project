<?php
// garage list + recall ingest helpers
require_once(__DIR__ . '/auth_logic.php');

// user id from username
function getUidByUsername($name)
{
    $u = trim((string) $name);
    if ($u == '') {
        return null;
    }
    $conn = auth_db();
    if (!$conn) {
        return null;
    }

    $st = $conn->prepare("SELECT id FROM users WHERE username=? LIMIT 1");
    if (!$st) {
        return null;
    }
    $st->bind_param("s", $u);
    $st->execute();
    $st->bind_result($id);
    if (!$st->fetch()) {
        $st->close();
        return null;
    }
    $st->close();
    return (int) $id;
}

// rows for garage page
function listUserCars($username)
{
    $uid = getUidByUsername($username);
    if ($uid === null) {
        return ['status' => 'ok', 'vehicles' => []];
    }

    $conn = auth_db();
    if (!$conn) {
        return ['status' => 'error', 'msg' => 'db_down'];
    }

    $q = "SELECT id, vin, car_make, model, vehicle_trim AS trim, color, year FROM user_vehicles WHERE user_id = ? ORDER BY id ASC";
    $st = $conn->prepare($q);
    if (!$st) {
        return ['status' => 'error', 'msg' => 'bad_query'];
    }

    $st->bind_param("i", $uid);
    $st->execute();
    $rs = $st->get_result();

    $out = [];
    $seen = [];
    $yearMax = (int) date('Y') + 1;
    while ($rs && ($r = $rs->fetch_assoc())) {
        // skip dup VIN rows
        $vin = strtoupper(trim((string) ($r['vin'] ?? '')));
        if ($vin !== '') {
            if (isset($seen[$vin])) {
                continue;
            }
            $seen[$vin] = 1;
        }

        $y = (int) ($r['year'] ?? 0);
        if ($y > $yearMax) {
            continue;
        }
        if ($y < 1950 && $y != 0) {
            continue;
        }

        if (!isset($r['trim']) || $r['trim'] === null) {
            $r['trim'] = '';
        }
        if (!isset($r['color']) || $r['color'] === null) {
            $r['color'] = '';
        }
        if (!isset($r['vin']) || $r['vin'] === null) {
            $r['vin'] = '';
        }
        $out[] = $r;
    }
    $st->close();
    return ['status' => 'ok', 'vehicles' => $out];
}

// sane triples for NHTSA
function buildRecallVehicleSet()
{
    $conn = auth_db();
    if (!$conn) {
        return [];
    }
    $sql = "SELECT car_make, model, year FROM user_vehicles WHERE TRIM(car_make)<>'' AND TRIM(model)<>'' AND year BETWEEN 1950 AND 2035";
    $res = $conn->query($sql);
    if (!$res) {
        return [];
    }

    $stopMake = ['unknown', 'n/a', 'na', 'none', 'test', 'demo', 'tbd', 'sample', 'xxx'];
    $stopModel = ['unknown', 'n/a', 'na', 'tbd', 'sample'];
    $tmp = [];

    while ($one = $res->fetch_assoc()) {
        $mk = trim((string) $one['car_make']);
        $md = trim((string) $one['model']);
        $yr = (int) $one['year'];
        if ($mk === '' || $md === '') {
            continue;
        }
        if (in_array(strtolower($mk), $stopMake, true)) {
            continue;
        }
        if (in_array(strtolower($md), $stopModel, true)) {
            continue;
        }
        if (preg_match('/^[0-9]{4}\s+/', $md) === 1) {
            continue;
        }

        $md = str_replace(['/', ',', '-'], ' ', $md);
        $md = trim(preg_replace('/\s+/', ' ', $md));
        if ($md === '') {
            continue;
        }
        if (strlen($md) > 40) {
            continue;
        }
        $tmp[] = [$mk, $md, $yr];
    }

    $out = [];
    foreach ($tmp as $r) {
        $seen = false;
        foreach ($out as $e) {
            if (strcasecmp($r[0], $e[0]) === 0 && strcasecmp($r[1], $e[1]) === 0 && (int) $r[2] === (int) $e[2]) {
                $seen = true;
                break;
            }
        }
        if (!$seen) {
            $out[] = $r;
        }
    }

    if (count($out) > 400) {
        $out = array_slice($out, 0, 400);
    }
    return $out;
}

// exec DMZ ingest script
function triggerRecallIngestNow()
{
    $root = realpath(__DIR__ . '/..');
    if ($root === false) {
        return ['status' => 'error', 'msg' => 'bad_root'];
    }
    $script = $root . '/DMZ/ingest_recalls.php';
    if (!file_exists($script)) {
        return ['status' => 'error', 'msg' => 'missing_ingest_script'];
    }

    $lockPath = sys_get_temp_dir() . '/recall_ingest.lock';
    // throttle double clicks
    if (file_exists($lockPath) && (time() - filemtime($lockPath)) < 90) {
        return ['status' => 'fail', 'msg' => 'already_running'];
    }
    @file_put_contents($lockPath, (string) time());

    $cmd = 'php ' . escapeshellarg($script) . ' 2>&1';
    $outLines = [];
    $code = 0;
    exec($cmd, $outLines, $code);
    @unlink($lockPath);

    $tail = '';
    if (!empty($outLines)) {
        $tail = trim(implode("\n", array_slice($outLines, -3)));
    }
    if ($code !== 0) {
        return ['status' => 'error', 'msg' => 'ingest_failed_try_again', 'code' => $code];
    }
    return ['status' => 'ok', 'msg' => 'ingest_done', 'out' => $tail];
}
