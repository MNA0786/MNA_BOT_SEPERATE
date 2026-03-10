<?php
// ==============================
// BACKUP SYSTEM
// ==============================

require_once 'config.php';
require_once 'database.php';
require_once 'telegram.php';
require_once 'utils.php'; // Make sure utils is loaded

function manual_backup($chat_id, $user_id) {
    if ($user_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    $msg = sendMessage($chat_id, "🔄 Starting manual backup...");
    $msg_id = $msg['result']['message_id'];
    
    $success = auto_backup();
    
    if ($success) {
        editMessage($chat_id, $msg_id, "✅ Backup completed successfully!\n\n📊 Files saved locally and uploaded to backup channel.");
    } else {
        editMessage($chat_id, $msg_id, "⚠️ Backup completed with warnings.\n\nCheck logs for details.");
    }
}

function auto_backup() {
    bot_log("Starting auto-backup...");
    
    $backup_dir = BACKUP_DIR . date('Y-m-d_H-i-s') . '/';
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $files_to_backup = [
        CSV_FILE => 'movies.csv',
        USERS_FILE => 'users.json',
        STATS_FILE => 'stats.json',
        REQUEST_FILE => 'requests.json',
        SETTINGS_FILE => 'settings.json',
        LOG_FILE => 'bot.log'
    ];
    
    $success = true;
    
    // Copy files to backup dir
    foreach ($files_to_backup as $source => $dest) {
        if (file_exists($source)) {
            $dest_path = $backup_dir . $dest;
            if (copy($source, $dest_path)) {
                bot_log("Backed up: {$dest}");
            } else {
                bot_log("Failed to backup: {$dest}", 'ERROR');
                $success = false;
            }
        }
    }
    
    // Create summary
    $summary = create_backup_summary();
    file_put_contents($backup_dir . 'summary.txt', $summary);
    
    // Upload to channel
    $channel_success = upload_backup_to_channel($backup_dir);
    
    // Clean old backups
    clean_old_backups();
    
    // Notify admin
    if ($success && $channel_success) {
        sendMessage(ADMIN_ID, "✅ Auto-backup completed successfully!\n📁 " . basename($backup_dir));
    } else {
        sendMessage(ADMIN_ID, "⚠️ Auto-backup completed with issues.\n📁 " . basename($backup_dir));
    }
    
    bot_log("Auto-backup completed");
    return $success && $channel_success;
}

function create_backup_summary() {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $summary = "📊 BACKUP SUMMARY\n";
    $summary .= "================\n\n";
    $summary .= "📅 Date: " . date('Y-m-d H:i:s') . "\n";
    $summary .= "🤖 Bot: Entertainment Tadka\n\n";
    
    $summary .= "📈 STATISTICS:\n";
    $summary .= "• Movies: " . ($stats['total_movies'] ?? 0) . "\n";
    $summary .= "• Users: " . count($users_data['users'] ?? []) . "\n";
    $summary .= "• Searches: " . ($stats['total_searches'] ?? 0) . "\n";
    $summary .= "• Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
    
    $summary .= "💾 FILES:\n";
    $summary .= "• movies.csv\n";
    $summary .= "• users.json\n";
    $summary .= "• stats.json\n";
    $summary .= "• requests.json\n";
    $summary .= "• settings.json\n";
    $summary .= "• bot.log\n";
    
    return $summary;
}

function upload_backup_to_channel($backup_dir) {
    $success = true;
    $files = glob($backup_dir . '*');
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $result = upload_file_to_channel($file);
            if (!$result) {
                $success = false;
            }
            sleep(2); // Rate limiting
        }
    }
    
    return $success;
}

function upload_file_to_channel($file_path) {
    $file_name = basename($file_path);
    $file_size = filesize($file_path);
    $file_size_mb = round($file_size / (1024 * 1024), 2);
    
    $caption = "💾 Backup: {$file_name}\n";
    $caption .= "📅 " . date('Y-m-d H:i:s') . "\n";
    $caption .= "📊 Size: {$file_size_mb} MB\n";
    $caption .= "🔒 Auto-backup";
    
    $post_fields = [
        'chat_id' => BACKUP_CHANNEL_ID,
        'document' => new CURLFile($file_path),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.telegram.org/bot" . BOT_TOKEN . "/sendDocument");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result_data = json_decode($result, true);
    $success = ($http_code == 200 && $result_data && $result_data['ok']);
    
    if ($success) {
        bot_log("Uploaded to channel: {$file_name}");
    } else {
        bot_log("Failed to upload: {$file_name}", 'ERROR');
    }
    
    return $success;
}

function clean_old_backups() {
    $backups = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    
    if (count($backups) > 7) {
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $to_delete = array_slice($backups, 0, count($backups) - 7);
        
        foreach ($to_delete as $dir) {
            $files = glob($dir . '/*');
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($dir);
            bot_log("Deleted old backup: " . basename($dir));
        }
    }
}

function backup_status($chat_id, $user_id) {
    if ($user_id != ADMIN_ID) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    $backups = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    $total_size = 0;
    
    foreach ($backups as $backup) {
        $files = glob($backup . '/*');
        foreach ($files as $file) {
            $total_size += filesize($file);
        }
    }
    
    $latest = !empty($backups) ? basename($backups[count($backups) - 1]) : 'None';
    $size_mb = round($total_size / (1024 * 1024), 2);
    
    $msg = "💾 <b>Backup Status</b>\n\n";
    $msg .= "📁 Total Backups: " . count($backups) . "\n";
    $msg .= "💽 Total Size: {$size_mb} MB\n";
    $msg .= "🕒 Latest: {$latest}\n";
    $msg .= "📡 Channel: " . BACKUP_CHANNEL_USERNAME . "\n\n";
    $msg .= "⏰ Auto-backup: Daily at " . AUTO_BACKUP_HOUR . ":00";
    
    sendMessage($chat_id, $msg, null, 'HTML');
}
?>
