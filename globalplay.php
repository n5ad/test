<?php
/*
 * globalplay.php
 * Immediate global playback on one node, or every local node when node=all.
 */
require_once __DIR__ . '/auth_check.inc.php';
require_once __DIR__ . '/nodes.inc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}
if (empty($_POST['file'])) {
    echo "No file specified.";
    exit;
}

$base = basename($_POST['file']);
$base_name = pathinfo($base, PATHINFO_FILENAME);
$node = trim($_POST['node'] ?? '');
$allowed = announcement_mgr_load_nodes();
if (!$allowed) {
    echo "No nodes configured in /etc/asterisk/local/announcement-nodes.conf";
    exit;
}
if ($node === '') {
    $node = $allowed[0];
}
if ($node === 'all') {
    $targets = $allowed;
} elseif (!announcement_mgr_node_allowed($node)) {
    echo "Node $node is not in /etc/asterisk/local/announcement-nodes.conf";
    exit;
} else {
    $targets = [$node];
}

$play_path = '/mp3/' . $base_name;
$play_script = '/etc/asterisk/local/playglobal.sh';
if (!is_executable($play_script)) {
    echo "playglobal.sh not found or not executable at $play_script.";
    exit;
}

$messages = [];
foreach ($targets as $target) {
    $cmd = 'sudo ' . escapeshellarg($play_script) . ' ' . escapeshellarg($target) . ' ' . escapeshellarg($play_path);
    exec($cmd . ' 2>&1', $output, $retval);
    if ($retval === 0) {
        $messages[] = "Global playback started for '$base_name' on node $target.";
    } else {
        $messages[] = "Failed to play '$base_name' globally on node $target.\nCode: $retval\nCmd: $cmd\nOutput: " . implode("\n", $output);
    }
    $output = [];
}
echo implode("\n", $messages);
