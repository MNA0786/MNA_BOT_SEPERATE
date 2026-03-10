<?php
// ==============================
// CRON JOB HANDLER
// ==============================

require_once 'config.php';
require_once 'database.php';
require_once 'auto_scan.php';
require_once 'backup.php';
require_once 'file_delete.php';

$task = $_GET['task'] ?? '';

// Verify secret token (optional security)
$secret = $_GET['secret'] ?? '';
$expected_secret = getenv('CRON_SECRET') ?: 'your-secret-key-here';

if ($secret !== $expected_secret && $expected_secret !== 'your-secret-key-here') {
    http_response_code(403);
    die('Unauthorized');
}

switch ($task) {
    case 'backup':
        auto_backup();
        echo "Backup completed at " . date('Y-m-d H:i:s');
        break;
        
    case 'scan':
        scan_all_channels();
        echo "Channel scan completed at " . date('Y-m-d H:i:s');
        break;
        
    case 'timers':
        process_delete_timers();
        echo "Timers processed at " . date('Y-m-d H:i:s');
        break;
        
    default:
        echo "No task specified";
}
?>