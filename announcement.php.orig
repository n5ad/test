<?php
/**
 * announcement.php - ASL3 Final Version (hardened)
 * Created by N5AD
 * Multi-node: cron line is sudo play-script <node> <file>
 */
require_once __DIR__ . '/auth_check.inc.php';
require_once __DIR__ . '/cron_validate.inc.php';
require_once __DIR__ . '/nodes.inc.php';

$TMP_DIR        = '/mp3';
$CONVERT_SCRIPT = '/etc/asterisk/local/audio_convert.sh';
$SOUNDS_DIR     = '/usr/local/share/asterisk/sounds/announcements';

$PLAY_SCRIPTS = [
    'local' => [
        'polite'   => '/etc/asterisk/local/polite_play.sh',
        'priority' => '/etc/asterisk/local/playaudio.sh'
    ],
    'global' => [
        'polite'   => '/etc/asterisk/local/polite_global.sh',
        'priority' => '/etc/asterisk/local/playglobal.sh'
    ]
];

$mp3     = isset($_POST['file']) ? basename($_POST['file']) : '';
$min     = $_POST['min']    ?? '';
$hour    = $_POST['hour']   ?? '';
$dom     = $_POST['dom']    ?? '*';
$month   = $_POST['month']  ?? '*';
$dow     = $_POST['dow']    ?? '*';
$week    = $_POST['week']   ?? '*';
$use_nth = !empty($_POST['use_nth']) && $_POST['use_nth'] == 1;
$desc    = $_POST['desc']   ?? 'Announcement';
$scope   = $_POST['scope']  ?? 'local';
$mode    = $_POST['mode']   ?? 'polite';
$node    = trim($_POST['node'] ?? '');
$pause_seconds = isset($_POST['pause']) ? floatval($_POST['pause']) : 0;

if (!$mp3) {
    die("Error: No audio file specified.");
}

$scope = strtolower(trim($scope));
$mode  = strtolower(trim($mode));
if (!in_array($scope, ['local', 'global'], true)) {
    die("Error: Invalid scope (use local or global).");
}
if (!in_array($mode, ['polite', 'priority'], true)) {
    die("Error: Invalid mode (use polite or priority).");
}
$desc = announcement_mgr_safe_desc($desc);

$allowed = announcement_mgr_load_nodes();
if (!$allowed) {
    die("Error: No nodes configured. Re-run the installer and enter the node numbers.");
}
if ($node === '') {
    $node = $allowed[0];
}
if ($node === 'all') {
    if ($scope === 'global') {
        die("Error: Global playback must target one node. Use local + all for every node on this server.");
    }
    $targets = $allowed;
} else {
    if (!preg_match('/^[0-9]+$/', $node) || !announcement_mgr_node_allowed($node)) {
        die("Error: Node $node is not in /etc/asterisk/local/announcement-nodes.conf");
    }
    $targets = [$node];
}

if ($min !== '' && $hour !== '') {
    $err = announcement_mgr_validate_schedule($min, $hour, $dom, $month, $dow, $week, $use_nth);
    if ($err !== null) {
        die("Error: $err");
    }
}

$src_file = "$TMP_DIR/$mp3";
if (!file_exists($src_file)) {
    die("Error: Source file not found: $src_file");
}
if (!is_executable($CONVERT_SCRIPT)) {
    die("Error: Conversion script not found or not executable: $CONVERT_SCRIPT");
}

$base_name = pathinfo($mp3, PATHINFO_FILENAME);
$ul_file   = "$SOUNDS_DIR/$base_name.ul";

$cmd_convert = "sudo " . escapeshellarg($CONVERT_SCRIPT) . " "
             . escapeshellarg($src_file) . " "
             . escapeshellarg($ul_file);
if ($pause_seconds > 0) {
    $cmd_convert .= " " . escapeshellarg($pause_seconds);
}
exec($cmd_convert . " 2>&1", $output, $ret);
if ($ret !== 0 || !file_exists($ul_file)) {
    echo "Error: Audio conversion failed.\n";
    echo "Command: $cmd_convert\n";
    echo "Return code: $ret\n";
    echo "Output:\n" . implode("\n", $output) . "\n";
    die();
}

$play_script = $PLAY_SCRIPTS[$scope][$mode] ?? $PLAY_SCRIPTS['local']['polite'];
$scope_note = strtoupper($scope);
$mode_note  = strtoupper($mode);

if ($min !== '' && $hour !== '') {
    $play_target = "$SOUNDS_DIR/$base_name";
    $tmp_cron = tempnam(sys_get_temp_dir(), 'ann_cron_');
    @chmod($tmp_cron, 0600);
    exec("crontab -l 2>/dev/null > " . escapeshellarg($tmp_cron) . " || true");
    $existing = @file_get_contents($tmp_cron);
    if ($existing === false) { $existing = ""; }
    if ($existing !== "" && substr($existing, -1) !== "\n") {
        $existing .= "\n";
    }

    $written = [];
    foreach ($targets as $target_node) {
        $desc_clean = "# Announcement: $desc [$mode_note] [$scope_note] [NODE $target_node]";
        $play_cmd = "sudo $play_script $target_node $play_target";
        if ($use_nth && in_array($week, ['1','2','3','4','5'], true)) {
            $low  = ((int)$week - 1) * 7 + 1;
            $high = ($week == 5) ? 31 : $low + 6;
            $cond = "[ \$(date +\\%d) -ge $low ] && [ \$(date +\\%d) -le $high ]";
            $cron_line = "$min $hour * * $dow /bin/bash -c '$cond && $play_cmd'";
            $nth_suffix = ['','st','nd','rd','th','th'][(int)$week];
            $desc_clean .= " ({$week}{$nth_suffix} week - $dow)";
        } else {
            $cron_line = "$min $hour $dom $month $dow $play_cmd";
        }
        $existing .= $desc_clean . "\n" . $cron_line . "\n";
        $written[] = $cron_line;
    }
    file_put_contents($tmp_cron, $existing);
    exec("crontab " . escapeshellarg($tmp_cron) . " 2>&1", $cron_out, $cron_ret);
    @unlink($tmp_cron);
    if ($cron_ret !== 0) {
        die("Error: Failed to install cron job into user crontab.\n" . implode("\n", $cron_out));
    }

    echo "Announcement installed successfully!\n";
    if ($pause_seconds > 0) {
        echo "Pause  : {$pause_seconds} seconds at start\n";
    }
    echo "File   : $base_name.ul\n";
    echo "Mode   : $mode_note\n";
    echo "Scope  : $scope_note\n";
    echo "Node   : " . implode(", ", $targets) . "\n";
    echo "Schedule:\n" . implode("\n", $written) . "\n";
    echo "(Cron entry is in www-data user crontab)\n";
} else {
    echo "File converted successfully (no schedule set).\n";
}
echo "UL saved to: $ul_file\n";
