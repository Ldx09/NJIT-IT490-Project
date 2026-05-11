<?php
// recall board rabbit session
session_start();
set_time_limit(25);

if (!isset($_SESSION["username"]) || !isset($_SESSION["session_key"])) {
    header("Location: index.html");
    exit(0);
}

require_once('path.inc');
require_once('get_host_info.inc');
require_once('rabbitMQLib.inc');

$rabbit = new rabbitMQClient("testRabbitMQ.ini", "testServer");

$sessOk = null;
try {
    $sessOk = $rabbit->send_request([
        'type' => 'validate_session',
        'session_key' => $_SESSION["session_key"],
    ]);
} catch (Exception $e) {
    session_destroy();
    header("Location: index.html?error=unavailable");
    exit(0);
}
if (!is_array($sessOk) || !isset($sessOk["status"]) || $sessOk["status"] !== "ok") {
    session_destroy();
    header("Location: index.html");
    exit(0);
}

$flash = '';
$tid = isset($_GET['thread']) ? (int) $_GET['thread'] : 0;

// post new thread or reply
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['board_action']) && $_POST['board_action'] === 'new_thread') {
        $topic = trim((string) ($_POST['title'] ?? ''));
        $whip = trim((string) ($_POST['car_info'] ?? ''));
        $opening = trim((string) ($_POST['body'] ?? ''));
        if ($topic === '' || $opening === '') {
            $flash = 'need a title and something in the box';
        } else {
            $out = null;
            try {
                $out = $rabbit->send_request([
                    'type' => 'CAR_ADD_THREAD',
                    'session_key' => $_SESSION["session_key"],
                    'title' => $topic,
                    'car_info' => $whip,
                    'body' => $opening,
                ]);
            } catch (Exception $ignore) {
                $out = null;
            }
            if (is_array($out) && isset($out['status']) && $out['status'] === 'ok' && !empty($out['thread_id'])) {
                header('Location: car_board.php?thread=' . (int) $out['thread_id']);
                exit(0);
            }
            $flash = 'didnt go thru — rabbit listener / db?';
            if (is_array($out) && isset($out['status']) && $out['status'] === 'fail' && isset($out['reason'])) {
                if ($out['reason'] === 'car_info_needs_number') {
                    $flash = 'car line needs digit or leave blank';
                } elseif ($out['reason'] === 'need_body') {
                    $flash = 'need a title and something in the box';
                }
            }
        }
    } elseif (isset($_POST['board_action']) && $_POST['board_action'] === 'add_reply') {
        $replyTid = (int) ($_POST['thread_id'] ?? 0);
        $replyTxt = trim((string) ($_POST['body'] ?? ''));
        if ($replyTid <= 0 || $replyTxt === '') {
            $flash = 'reply empty';
        } else {
            try {
                $out = $rabbit->send_request([
                    'type' => 'CAR_ADD_REPLY',
                    'session_key' => $_SESSION["session_key"],
                    'thread_id' => $replyTid,
                    'body' => $replyTxt,
                ]);
            } catch (Exception $ex) {
                $out = null;
            }
            if (is_array($out) && isset($out['status']) && $out['status'] === 'ok') {
                header('Location: car_board.php?thread=' . $replyTid);
                exit(0);
            }
            $flash = 'reply didnt stick';
        }
    }
}

$allTopics = [];
$openThread = null;
$belowPosts = [];

// load one thread view
if ($tid > 0) {
    $got = null;
    try {
        $got = $rabbit->send_request([
            'type' => 'CAR_GET_THREAD',
            'thread_id' => $tid,
        ]);
    } catch (Exception $ignore) {
        $got = null;
    }
    if (is_array($got) && isset($got['status']) && $got['status'] === 'ok') {
        $openThread = $got['thread'];
        $belowPosts = $got['replies'] ?? [];
    } else {
        if ($flash === '') {
            $flash = 'no thread with that id';
        }
        $tid = 0;
    }
}

// load thread list
if ($tid <= 0) {
    $listed = $rabbit->send_request(['type' => 'CAR_LIST_THREADS', 'limit' => 40]);
    if (is_array($listed) && isset($listed['threads'])) {
        $allTopics = $listed['threads'];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>RecallShield — car chatter</title>
    <style>
        body { font-family: sans-serif; margin: 12px; }
        textarea { width: 95%; min-height: 90px; }
        input[type="text"] { width: 95%; padding: 4px; }
        th, td { border: 1px solid #aaa; padding: 6px; }
        table { border-collapse: collapse; margin-top: 10px; }
        .blob { background: #eee; padding: 8px; margin: 8px 0; }
        .small { color: #444; font-size: 14px; }
    </style>
</head>
<body>

<?php if ($openThread) { ?>
    <p><a href="car_board.php">back to list</a></p>
    <h2><?php echo htmlspecialchars($openThread['title']); ?></h2>
    <p class="small">whip: <?php echo htmlspecialchars($openThread['car_info'] ?: '(none)'); ?>
        — <?php echo htmlspecialchars($openThread['author']); ?> @ <?php echo htmlspecialchars($openThread['created_at']); ?></p>
    <div class="blob"><?php echo nl2br(htmlspecialchars($openThread['body'])); ?></div>

    <h3>below</h3>
    <?php if ($flash !== '') { ?>
        <p style="color:#800"><?php echo htmlspecialchars($flash); ?></p>
    <?php } ?>
    <?php if (empty($belowPosts)) { ?>
        <p>nobody replied yet</p>
    <?php } else {
        foreach ($belowPosts as $rep) { ?>
            <div class="blob">
                <span class="small"><?php echo htmlspecialchars($rep['author']); ?> · <?php echo htmlspecialchars($rep['created_at']); ?></span><br>
                <?php echo nl2br(htmlspecialchars($rep['body'])); ?>
            </div>
        <?php }
    } ?>

    <h4>your reply</h4>
    <form method="post">
        <input type="hidden" name="board_action" value="add_reply">
        <input type="hidden" name="thread_id" value="<?php echo (int) $openThread['id']; ?>">
        <textarea name="body" required></textarea><br>
        <button type="submit">send</button>
    </form>

<?php } else { ?>
    <h2>car chatter ( RecallShield / IT490 )</h2>
    <p>garage talk — what youre driving, recall headaches, dealer stories. demo board.</p>

    <?php if ($flash !== '') { ?>
        <p class="small" style="color:#600"><?php echo htmlspecialchars($flash); ?></p>
    <?php } ?>

    <h3>start thread</h3>
    <form method="post">
        <input type="hidden" name="board_action" value="new_thread">
        title<br>
        <input type="text" name="title" required><br>
        car (optional — if you type anything, include a year or #)<br>
        <input type="text" name="car_info" placeholder="2012 whatever"><br>
        first post<br>
        <textarea name="body" required></textarea><br>
        <button type="submit">post it</button>
    </form>

    <h3>threads</h3>
    <?php if (empty($allTopics)) { ?>
        <p>crickets — you go first</p>
    <?php } else { ?>
        <table style="width:100%;max-width:700px">
            <tr><th>title</th><th>car</th><th>who</th><th>when</th></tr>
            <?php foreach ($allTopics as $t) {
                $id = (int) $t['id'];
                echo '<tr><td><a href="car_board.php?thread=' . $id . '">' . htmlspecialchars($t['title']) . '</a></td>';
                echo '<td>' . htmlspecialchars($t['car_info'] ?? '') . '</td>';
                echo '<td>' . htmlspecialchars($t['username']) . '</td>';
                echo '<td>' . htmlspecialchars($t['created_at']) . '</td></tr>';
            } ?>
        </table>
    <?php } ?>
<?php } ?>

<p><a href="home.php">home</a> | <a href="appointments.php">appointments</a> | <a href="logout.php">logout</a></p>
</body>
</html>
