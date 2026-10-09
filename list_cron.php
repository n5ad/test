<?php
/*
 * list_cron.php
 * Reads www-data user crontab and returns node + scope.
 */
require_once __DIR__ . '/auth_check.inc.php';

header('Content-Type: application/json');

$cron = shell_exec('crontab -l 2>/dev/null');
if ($cron === null || trim($cron) === '') {
    echo json_encode([]);
    exit;
}

$lines = explode("\n", $cron);
$entries = [];
$last_comment = "";

foreach ($lines as $line) {
    $line = trim($line);
    if ($line === "") continue;

    if (strpos($line, '# Announcement:') === 0) {
        $last_comment = trim(str_replace('# Announcement:', '', $line));
        continue;
    }

    if (strpos($line, 'playaudio.sh') === false &&
        strpos($line, 'playglobal.sh') === false &&
        strpos($line, 'polite_play.sh') === false &&
        strpos($line, 'polite_global.sh') === false) {
        continue;
    }

    $parts = preg_split('/\s+/', $line, -1, PREG_SPLIT_NO_EMPTY);
    $time = implode(" ", array_slice($parts, 0, 5));
    $command = implode(" ", array_slice($parts, 5));

    $script = '';
    $node = '';
    $file = '';

    if (preg_match('/(?:sudo\s+)?(?:\S*\/)?(playaudio\.sh|playglobal\.sh|polite_play\.sh|polite_global\.sh)\s+([0-9]+)\s+(\S+)/', $command, $matches)) {
        $script = $matches[1];
        $node = $matches[2];
        $file = basename($matches[3]);
    } elseif (preg_match('/(?:sudo\s+)?(?:\S*\/)?(playaudio\.sh|playglobal\.sh|polite_play\.sh|polite_global\.sh)\s+(\S+)/', $command, $matches)) {
        $script = $matches[1];
        $file = basename($matches[2]);
    } else {
        continue;
    }

    $file = preg_replace('/\.ul$/', '', $file);
    $scope = (strpos($script, 'global') !== false) ? 'global' : 'local';
    if (preg_match('/\[GLOBAL\]/i', $last_comment)) {
        $scope = 'global';
    } elseif (preg_match('/\[local\]/i', $last_comment)) {
        $scope = 'local';
    }
    if ($node === '' && preg_match('/\[NODE\s+([0-9]+)\]/i', $last_comment, $nm)) {
        $node = $nm[1];
    }

    $entries[] = [
        "time"   => $time,
        "file"   => $file,
        "desc"   => $last_comment ?: 'No description',
        "scope"  => $scope,
        "node"   => $node,
        "raw"    => $line,
        "script" => $script
    ];
    $last_comment = "";
}

echo json_encode($entries);
