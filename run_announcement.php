<?php
/*
 * run_announcement.php
 * Play Now. Requires a node from announcement-nodes.conf.
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

$SOUNDS_DIR = '/usr/local/share/asterisk/sounds/announcements';
$base = basename($_POST['file']);
$base_name = pathinfo($base, PATHINFO_FILENAME);
$scope  = strtolower(trim($_POST['scope'] ?? 'local'));
$source = strtolower(trim($_POST['source'] ?? 'ul'));
$node   = trim($_POST['node'] ?? '');

$allowed = announcement_mgr_load_nodes();
if (!$allowed) {
    echo "No nodes configured in /etc/asterisk/local/announcement-nodes.conf";
    exit;
}
if ($node === '') {
    $node = $allowed[0];
}
if ($node === 'all') {
    if ($scope === 'global') {
        echo "Global play must target one node.";
        exit;
    }
    $targets = $allowed;
} elseif (!announcement_mgr_node_allowed($node)) {
    echo "Node $node is not allowed.";
    exit;
} else {
    $targets = [$node];
}

if ($source === 'mp3') {
    $play_path = '/mp3/' . $base_name;
} else {
    $full = $SOUNDS_DIR . '/' . $base_name;
    $play_path = (file_exists($full . '.ul') || file_exists($full)) ? $full : 'announcements/' . $base_name;
}

if ($scope === 'global') {
    $play_script = '/etc/asterisk/local/playglobal.sh';
} else {
    $play_script = '/etc/asterisk/local/playaudio.sh';
}
if (!is_executable($play_script)) {
    echo "Playback script not found or not executable: $play_script";
    exit;
}

$messages = [];
foreach ($targets as $target) {
    $cmd = 'sudo ' . escapeshellarg($play_script) . ' ' . escapeshellarg($target) . ' ' . escapeshellarg($play_path);
    exec($cmd . ' 2>&1', $output, $retval);
    if ($retval === 0) {
        $messages[] = ($scope === 'global')
            ? "Playing '$base_name' globally on node $target."
            : "Playing '$base_name' locally on node $target.";
    } else {
        $messages[] = "Failed on node $target.\nCode: $retval\nOutput: " . implode("\n", $output);
    }
    $output = [];
}
echo implode("\n", $messages);
