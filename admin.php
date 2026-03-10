<?php
// ==============================
// ADMIN COMMANDS & FUNCTIONS
// ==============================

require_once 'config.php';
require_once 'database.php';
require_once 'telegram.php';
require_once 'users.php';
require_once 'backup.php';
require_once 'auto_scan.php';

function is_admin($user_id) {
    return ($user_id == ADMIN_ID);
}

// ==============================
// ADMIN STATISTICS
// ==============================

function admin_stats($chat_id, $user_id) {
    if (!is_admin($user_id)) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    
    $total_users = count($users_data['users'] ?? []);
    $active_today = count_active_users_today();
    $pending_requests = count($requests_data['requests'] ?? []);
    
    $msg = "📊 <b>Bot Statistics</b>\n\n";
    
    $msg .= "🎬 <b>Movies:</b>\n";
    $msg .= "• Total: " . ($stats['total_movies'] ?? 0) . "\n";
    $msg .= "• CSV Size: " . format_bytes(filesize(CSV_FILE)) . "\n\n";
    
    $msg .= "👥 <b>Users:</b>\n";
    $msg .= "• Total: {$total_users}\n";
    $msg .= "• Active Today: {$active_today}\n\n";
    
    $msg .= "🔍 <b>Searches:</b>\n";
    $msg .= "• Total: " . ($stats['total_searches'] ?? 0) . "\n";
    $msg .= "• Successful: " . ($stats['successful_searches'] ?? 0) . "\n";
    $msg .= "• Failed: " . ($stats['failed_searches'] ?? 0) . "\n\n";
    
    $msg .= "📥 <b>Downloads:</b> " . ($stats['total_downloads'] ?? 0) . "\n";
    $msg .= "📝 <b>Pending Requests:</b> {$pending_requests}\n\n";
    
    $msg .= "💾 <b>System:</b>\n";
    $msg .= "• Last Updated: " . ($stats['last_updated'] ?? 'N/A') . "\n";
    $msg .= "• PHP Version: " . phpversion() . "\n";
    $msg .= "• Memory: " . format_bytes(memory_get_usage(true));
    
    sendMessage($chat_id, $msg, null, 'HTML');
}

function count_active_users_today() {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $today = date('Y-m-d');
    $count = 0;
    
    foreach ($users_data['users'] ?? [] as $user) {
        if (strpos($user['last_active'] ?? '', $today) === 0) {
            $count++;
        }
    }
    
    return $count;
}

function format_bytes($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    return round($bytes / 1048576, 2) . ' MB';
}

// ==============================
// CSV MANAGEMENT
// ==============================

function show_csv_data($chat_id, $user_id, $show_all = false) {
    if (!is_admin($user_id)) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    if (!file_exists(CSV_FILE)) {
        sendMessage($chat_id, "❌ CSV file not found.");
        return;
    }
    
    $data = get_all_movies_list();
    $total = count($data);
    $display = $show_all ? $data : array_slice($data, -20);
    
    $msg = "📊 <b>CSV Database</b>\n\n";
    $msg .= "📁 Total Movies: {$total}\n";
    $msg .= "📋 Showing: " . count($display) . " entries\n\n";
    
    foreach ($display as $index => $movie) {
        $num = $show_all ? $index + 1 : $total - 20 + $index + 1;
        $msg .= "<b>{$num}.</b> {$movie['movie_name']}\n";
        $msg .= "   📝 ID: {$movie['message_id_raw']} | {$movie['quality']}\n";
        
        if (strlen($msg) > 3500) {
            sendMessage($chat_id, $msg, null, 'HTML');
            $msg = "📊 Continued...\n\n";
        }
    }
    
    if (!empty($msg)) {
        sendMessage($chat_id, $msg, null, 'HTML');
    }
}

// ==============================
// BROADCAST SYSTEM
// ==============================

function send_broadcast($chat_id, $user_id, $message) {
    if (!is_admin($user_id)) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    if (empty($message)) {
        sendMessage($chat_id, "❌ Usage: /broadcast your message");
        return;
    }
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $total = count($users_data['users'] ?? []);
    
    if ($total == 0) {
        sendMessage($chat_id, "📭 No users to broadcast to.");
        return;
    }
    
    // Confirmation
    $keyboard = [
        'inline_keyboard' => [[
            ['text' => '✅ Confirm Broadcast', 'callback_data' => 'confirm_broadcast_' . base64_encode($message)],
            ['text' => '❌ Cancel', 'callback_data' => 'cancel_broadcast']
        ]]
    ];
    
    $msg = "📢 <b>Broadcast Preview</b>\n\n";
    $msg .= "To: <b>{$total}</b> users\n\n";
    $msg .= "Message:\n{$message}\n\n";
    $msg .= "⚠️ This action cannot be undone. Confirm?";
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

function execute_broadcast($chat_id, $user_id, $message_encoded) {
    if (!is_admin($user_id)) return;
    
    $message = base64_decode($message_encoded);
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    $progress_msg = sendMessage($chat_id, "📢 Broadcasting... 0%");
    $progress_id = $progress_msg['result']['message_id'];
    
    $success = 0;
    $failed = 0;
    $total = count($users_data['users'] ?? []);
    $i = 0;
    
    foreach ($users_data['users'] as $uid => $user) {
        try {
            sendMessage($uid, "📢 <b>Announcement</b>\n\n{$message}", null, 'HTML');
            $success++;
        } catch (Exception $e) {
            $failed++;
        }
        
        $i++;
        if ($i % 10 == 0) {
            $percent = round(($i / $total) * 100);
            editMessage($chat_id, $progress_id, "📢 Broadcasting... {$percent}%");
        }
        
        usleep(100000);
    }
    
    $final = "✅ Broadcast complete!\n\n";
    $final .= "📊 Sent to: {$success}/{$total}\n";
    $final .= "❌ Failed: {$failed}";
    
    editMessage($chat_id, $progress_id, $final);
    bot_log("Broadcast sent to {$success} users");
}

// ==============================
// SYSTEM MAINTENANCE
// ==============================

function perform_cleanup($chat_id, $user_id) {
    if (!is_admin($user_id)) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    $msg = sendMessage($chat_id, "🧹 Cleaning up system...");
    $msg_id = $msg['result']['message_id'];
    
    // Clear cache
    global $movie_cache, $movie_messages;
    $movie_cache = [];
    $movie_messages = [];
    
    // Reload CSV
    load_and_clean_csv();
    
    // Clean old backups
    $backup_dirs = glob(BACKUP_DIR . '*', GLOB_ONLYDIR);
    $deleted = 0;
    
    if (count($backup_dirs) > 7) {
        usort($backup_dirs, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        foreach (array_slice($backup_dirs, 0, count($backup_dirs) - 7) as $dir) {
            array_map('unlink', glob("$dir/*"));
            rmdir($dir);
            $deleted++;
        }
    }
    
    // Optimize CSV
    $data = get_all_movies_list();
    $handle = fopen(CSV_FILE, 'w');
    fputcsv($handle, ['movie_name','message_id','date','video_path','quality','size','language','channel_type','channel_id','channel_username']);
    foreach ($data as $row) {
        fputcsv($handle, [
            $row['movie_name'], $row['message_id_raw'], $row['date'], $row['video_path'],
            $row['quality'], $row['size'], $row['language'], $row['channel_type'],
            $row['channel_id'], $row['channel_username']
        ]);
    }
    fclose($handle);
    
    $final = "✅ Cleanup complete!\n\n";
    $final .= "• Cache cleared\n";
    $final .= "• CSV optimized (" . count($data) . " movies)\n";
    $final .= "• Old backups removed: {$deleted}";
    
    editMessage($chat_id, $msg_id, $final);
    bot_log("Cleanup performed by admin");
}

function toggle_maintenance_mode($chat_id, $user_id, $mode) {
    global $MAINTENANCE_MODE;
    
    if (!is_admin($user_id)) {
        sendMessage($chat_id, "❌ Access denied.");
        return;
    }
    
    if ($mode == 'on') {
        $MAINTENANCE_MODE = true;
        sendMessage($chat_id, "🔧 Maintenance mode ENABLED");
    } elseif ($mode == 'off') {
        $MAINTENANCE_MODE = false;
        sendMessage($chat_id, "✅ Maintenance mode DISABLED");
    } else {
        sendMessage($chat_id, "❌ Usage: /maintenance on|off");
    }
    
    bot_log("Maintenance mode: " . ($mode == 'on' ? 'ON' : 'OFF'));
}

// ==============================
// QUICK ADD (ADMIN ONLY)
// ==============================

function quick_add_movie($chat_id, $user_id, $input) {
    if (!is_admin($user_id)) {
        sendMessage($chat_id, "❌ Access denied. Admin only command.");
        return;
    }
    
    $parts = explode(',', $input, 3);
    
    if (count($parts) < 3) {
        show_quickadd_format($chat_id);
        return;
    }
    
    $movie_name = trim($parts[0]);
    $message_id = trim($parts[1]);
    $channel_info = trim($parts[2]);
    
    if (!is_numeric($message_id)) {
        sendMessage($chat_id, "❌ Invalid message ID.");
        return;
    }
    
    // Determine channel
    $channel_type = 'other';
    $channel_id = '';
    $channel_username = '';
    
    if (strpos($channel_info, '@') === 0) {
        $channel_username = $channel_info;
        $channel_id = get_channel_id_by_username($channel_username);
        $channel_type = $channel_id ? get_channel_type_by_id($channel_id) : 'other';
    } elseif (is_numeric($channel_info) || strpos($channel_info, '-100') === 0) {
        $channel_id = $channel_info;
        $channel_type = get_channel_type_by_id($channel_id);
        $channel_info = get_channel_info_by_type($channel_type);
        $channel_username = $channel_info['username'];
    } else {
        sendMessage($chat_id, "❌ Invalid channel format.");
        return;
    }
    
    // Auto-detect quality
    $quality = detect_quality_from_name($movie_name);
    $language = detect_language_from_name($movie_name);
    $size = detect_size_from_name($movie_name);
    
    // Add to CSV
    $entry = [
        $movie_name, $message_id, date('d-m-Y'), '', 
        $quality, $size, $language, $channel_type, $channel_id, $channel_username
    ];
    
    $handle = fopen(CSV_FILE, "a");
    if ($handle) {
        fputcsv($handle, $entry);
        fclose($handle);
        
        // Update cache
        global $movie_cache;
        $movie_cache = [];
        
        $success = "✅ <b>Movie Added!</b>\n\n";
        $success .= "🎬 {$movie_name}\n";
        $success .= "📝 ID: {$message_id}\n";
        $success .= "📊 {$quality} | {$language}\n";
        $success .= "🎭 " . get_channel_display_name($channel_type, $user_id) . "\n\n";
        $success .= "🔗 " . get_direct_channel_link($message_id, $channel_id);
        
        sendMessage($chat_id, $success, null, 'HTML');
        bot_log("Quick add by admin: {$movie_name}");
    } else {
        sendMessage($chat_id, "❌ Failed to add movie.");
    }
}

function detect_quality_from_name($name) {
    $name = strtolower($name);
    if (strpos($name, '1080') !== false) return '1080p';
    if (strpos($name, '720') !== false) return '720p';
    if (strpos($name, '480') !== false) return '480p';
    if (strpos($name, 'theater') !== false) return 'Theater';
    if (strpos($name, 'print') !== false) return 'Theater';
    return 'HD';
}

function detect_language_from_name($name) {
    $name = strtolower($name);
    if (strpos($name, 'hindi') !== false) return 'Hindi';
    if (strpos($name, 'english') !== false) return 'English';
    if (strpos($name, 'tamil') !== false) return 'Tamil';
    if (strpos($name, 'telugu') !== false) return 'Telugu';
    return 'Hindi';
}

function detect_size_from_name($name) {
    $name = strtolower($name);
    if (preg_match('/(\d+(?:\.\d+)?)\s*(gb|mb)/i', $name, $matches)) {
        return $matches[1] . strtoupper($matches[2]);
    }
    return 'Unknown';
}

function show_quickadd_format($chat_id) {
    $format = "🎬 <b>Quick Add Format:</b>\n\n";
    $format .= "<code>/quickadd movie_name,message_id,channel</code>\n\n";
    $format .= "<b>Examples:</b>\n";
    $format .= "• <code>/quickadd KGF 2 (2024),12345,@EntertainmentTadka786</code>\n";
    $format .= "• <code>/quickadd Animal (2023),67890,-1003181705395</code>\n\n";
    $format .= "<b>Supported Channels:</b>\n";
    $format .= "• @EntertainmentTadka786 (Main)\n";
    $format .= "• @Entertainment_Tadka_Serial_786 (Serials)\n";
    $format .= "• @threater_print_movies (Theater)\n";
    $format .= "• @ETBackup (Backup)\n";
    $format .= "• -1003251791991 (Private 1)\n";
    $format .= "• -1002337293281 (Private 2)";
    
    sendMessage($chat_id, $format, null, 'HTML');
}
?>