<?php
/*
 * update_announcement.php
 * Rebuilds the schedule and keeps the existing node argument.
 */
require_once __DIR__ . '/auth_check.inc.php';
require_once __DIR__ . '/cron_validate.inc.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed.";
    exit;
}

$raw_line = trim($_POST['raw_line'] ?? '');
$min      = trim($_POST['min']      ?? '');
$hour     = trim($_POST['hour']     ?? '');
$dom      = trim($_POST['dom']      ?? '');
$month    = trim($_POST['month']    ?? '');
$dow      = trim($_POST['dow']      ?? '');
$week     = trim($_POST['week']     ?? '*');
$use_nth  = !empty($_POST['use_nth']) && $_POST['use_nth'] == 1;

if (!$raw_line || $min === '' || $hour === '' || $dom === '' || $month === '' || $dow === '') {
    echo "Missing required fields.";
    exit;
}
$err = announcement_mgr_validate_schedule($min, $hour, $dom, $month, $dow, $week, $use_nth);
if ($err !== null) {
    echo "Error: $err";
    exit;
}

$output = [];
exec('crontab -l 2>/dev/null', $output, $retval);

$new_crontab = [];
$found = false;
$comment_line = null;

foreach ($output as $line) {
    $trimmed = trim($line);
    if (strpos($trimmed, '# Announcement:') === 0) {
        $comment_line = $trimmed;
        continue;
    }

    if ($trimmed === $raw_line || strpos($trimmed, $raw_line) !== false) {
        $found = true;
        if (preg_match('/^\S+\s+\S+\s+\S+\s+\S+\s+\S+\s+(.+)$/', $line, $matches)) {
            $full_command = trim($matches[1]);
        } else {
            $full_command = 'sudo /etc/asterisk/local/playaudio.sh';
        }

        $play_cmd = $full_command;
        $play_re = '/(?:sudo\s+)?(\/(?:etc\/asterisk\/local\/(?:playaudio|playglobal|polite_play|polite_global)\.sh)(?:\s+\S+){1,2})/';
        if (preg_match($play_re, $full_command, $play_matches)) {
            $play_cmd = 'sudo ' . trim($play_matches[1]);
        } elseif (strpos($play_cmd, 'sudo ') !== 0) {
            $play_cmd = 'sudo ' . $play_cmd;
        }
        $play_cmd = trim($play_cmd, " ')\t");

        if ($use_nth && in_array($week, ['1','2','3','4','5'], true) && preg_match('/^[1-7]$/', trim($dow))) {
            $low  = ((int)$week - 1) * 7 + 1;
            $high = ((int)$week === 5) ? 31 : $low + 6;
            $cond = "[ \$(date +\\%d) -ge $low ] && [ \$(date +\\%d) -le $high ]";
            $new_line = "$min $hour * * $dow /bin/bash -c '$cond && $play_cmd'";
        } else {
            $new_line = "$min $hour $dom $month $dow $play_cmd";
        }

        if ($comment_line) {
            $new_crontab[] = $comment_line;
        }
        $new_crontab[] = $new_line;
        $comment_line = null;
    } else {
        if ($comment_line !== null) {
            $new_crontab[] = $comment_line;
            $comment_line = null;
        }
        $new_crontab[] = $line;
    }
}
if ($comment_line !== null) {
    $new_crontab[] = $comment_line;
}
if (!$found) {
    echo "Original cron line not found in crontab.";
    exit;
}

$tempfile = tempnam(sys_get_temp_dir(), 'cron_update_');
file_put_contents($tempfile, implode("\n", $new_crontab) . "\n");
exec("crontab " . escapeshellarg($tempfile), $out, $ret);
unlink($tempfile);
echo ($ret === 0) ? "Cron job updated successfully." : "Failed to update crontab.";
