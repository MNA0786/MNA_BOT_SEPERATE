<?php
// ==============================
// BACKGROUND WORKER FOR HEAVY TASKS
// ==============================

require_once 'config.php';
require_once 'database.php';
require_once 'auto_scan.php';

if (getenv('WORKER_MODE') !== 'true') {
    die("Worker mode not enabled");
}

while (true) {
    // Process pending tasks
    $tasks = get_pending_tasks();
    
    foreach ($tasks as $task) {
        switch ($task['type']) {
            case 'deep_scan':
                scan_channel_messages($task['channel_id'], $task['channel_type'], 500);
                break;
            case 'data_cleanup':
                perform_cleanup_in_background();
                break;
        }
        mark_task_completed($task['id']);
    }
    
    sleep(60); // Run every minute
}

function get_pending_tasks() {
    $file = __DIR__ . '/data/tasks.json';
    if (!file_exists($file)) return [];
    $tasks = json_decode(file_get_contents($file), true);
    return array_filter($tasks, function($t) {
        return !$t['completed'] && $t['scheduled_time'] <= time();
    });
}

function mark_task_completed($task_id) {
    $file = __DIR__ . '/data/tasks.json';
    $tasks = json_decode(file_get_contents($file), true);
    foreach ($tasks as &$task) {
        if ($task['id'] == $task_id) {
            $task['completed'] = true;
            $task['completed_time'] = time();
        }
    }
    file_put_contents($file, json_encode($tasks, JSON_PRETTY_PRINT));
}

function perform_cleanup_in_background() {
    // Heavy cleanup tasks
    $old_logs = glob(__DIR__ . '/data/*.log');
    foreach ($old_logs as $log) {
        if (filemtime($log) < time() - 30 * 86400) { // Older than 30 days
            unlink($log);
        }
    }
}
?>