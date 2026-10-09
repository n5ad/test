<?php
/**
 * list_nodes.php
 * Returns the node numbers written by the installer.
 */
require_once __DIR__ . '/auth_check.inc.php';
require_once __DIR__ . '/nodes.inc.php';

header('Content-Type: application/json');
echo json_encode(announcement_mgr_load_nodes());
