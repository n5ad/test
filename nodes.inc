<?php
/**
 * nodes.inc.php
 * Allowed AllStar node numbers for announcement-manager.
 */
if (defined('ANNOUNCEMENT_MGR_NODES_LOADED')) {
    return;
}
define('ANNOUNCEMENT_MGR_NODES_LOADED', true);

function announcement_mgr_nodes_file(): string {
    return '/etc/asterisk/local/announcement-nodes.conf';
}

function announcement_mgr_load_nodes(): array {
    $file = announcement_mgr_nodes_file();
    if (!is_readable($file)) {
        return [];
    }
    $nodes = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^[0-9]+$/', $line)) {
            $nodes[] = $line;
        }
    }
    return array_values(array_unique($nodes));
}

function announcement_mgr_node_allowed(string $node): bool {
    return in_array($node, announcement_mgr_load_nodes(), true);
}
