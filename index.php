<?php
// ==============================
// MAIN ENTRY POINT - FIXED
// ==============================

// ===== FIX: Check if constant already exists before defining =====
if (!defined('ON_RENDER')) {
    // Detect if running on Render.com
    if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'onrender.com') !== false) {
        define('ON_RENDER', true);
    } else {
        define('ON_RENDER', false);
    }
}

// ===== IMPORTANT: No output before headers! =====
// Yeh saari files require karni hain BEFORE any output
require_once 'config.php';
require_once 'utils.php';
require_once 'database.php';
require_once 'telegram.php';
require_once 'channels.php';
require_once 'users.php';
require_once 'movies.php';
require_once 'typing_eta.php';
require_once 'file_delete.php';
require_once 'settings.php';
require_once 'pagination.php';
require_once 'bulk_send.php';
require_once 'auto_scan.php';
require_once 'callback_handler.php';
require_once 'command_handler.php';
require_once 'admin.php';
require_once 'backup.php';

// ===== Headers - ABHI SET KARO (after requires but before any output) =====
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// ==============================
// MAINTENANCE MODE CHECK
// ==============================
global $MAINTENANCE_MODE, $MAINTENANCE_MESSAGE;
if ($MAINTENANCE_MODE && isset($_SERVER['REQUEST_METHOD'])) {
    // Check if it's a webhook request
    $input = file_get_contents('php://input');
    if (!empty($input)) {
        $update = json_decode($input, true);
        if (isset($update['message'])) {
            $chat_id = $update['message']['chat']['id'];
            sendMessage($chat_id, $MAINTENANCE_MESSAGE);
            exit;
        }
    }
}

// ==============================
// PROCESS WEBHOOK UPDATE
// ==============================
$update = json_decode(file_get_contents('php://input'), true);

if ($update) {
    // Initialize movie cache
    get_cached_movies();
    
    // ==================== CHANNEL POST HANDLING ====================
    if (isset($update['channel_post'])) {
        $post = $update['channel_post'];
        $chat_id = $post['chat']['id'];
        $message_id = $post['message_id'];
        
        // Determine channel type
        $channel_type = get_channel_type_by_id($chat_id);
        
        // Only process known channels
        if ($channel_type != 'other' && $channel_type != 'request_group') {
            $text = '';
            $quality = 'Unknown';
            $size = 'Unknown';
            $language = 'Hindi';
            
            if (isset($post['caption'])) {
                $text = $post['caption'];
                $quality = detect_quality_from_name($text);
                $language = detect_language_from_name($text);
            } elseif (isset($post['text'])) {
                $text = $post['text'];
            } elseif (isset($post['document'])) {
                $text = $post['document']['file_name'];
                $size = round($post['document']['file_size'] / (1024 * 1024), 2) . ' MB';
            }
            
            if (!empty(trim($text))) {
                append_movie($text, $message_id, date('d-m-Y'), '', $quality, $size, $language, $channel_type);
            }
        }
    }
    
    // ==================== MESSAGE HANDLING ====================
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $user_id = $message['from']['id'];
        $text = $message['text'] ?? '';
        $chat_type = $message['chat']['type'] ?? 'private';
        
        // Update user data
        $user_info = [
            'first_name' => $message['from']['first_name'] ?? '',
            'last_name' => $message['from']['last_name'] ?? '',
            'username' => $message['from']['username'] ?? ''
        ];
        update_user_data($user_id, $user_info);
        
        // Group message filtering
        if ($chat_type != 'private' && !empty($text) && strpos($text, '/') !== 0) {
            if (!is_valid_movie_query($text)) {
                // Ignore non-movie messages in groups
                exit;
            }
        }
        
        // Handle commands
        if (!empty($text)) {
            if (strpos($text, '/') === 0) {
                $parts = explode(' ', $text);
                $command = strtolower($parts[0]);
                $params = array_slice($parts, 1);
                
                // Remove bot username from command if present
                if (strpos($command, '@') !== false) {
                    $command = explode('@', $command)[0];
                }
                
                handle_command($chat_id, $user_id, $command, $params);
            } else {
                // Treat as search
                advanced_search($chat_id, $text, $user_id);
            }
        }
    }
    
    // ==================== CALLBACK QUERY HANDLING ====================
    if (isset($update['callback_query'])) {
        handle_callback_query($update['callback_query']);
    }
    
    // ==================== SCHEDULED TASKS ====================
    $current_hour = date('H');
    $current_minute = date('i');
    
    // Auto-backup at 3 AM
    if ($current_hour == AUTO_BACKUP_HOUR && $current_minute == '00') {
        auto_backup();
    }
    
    // Auto-scan channels every 6 hours
    if ($current_minute == '0' && in_array($current_hour, ['0', '6', '12', '18'])) {
        scan_all_channels();
    }
    
    // Process delete timers every minute
    if ($current_minute == '0') {
        process_delete_timers();
    }
}

// ==============================
// WEBHOOK SETUP PAGE
// ==============================
if (php_sapi_name() !== 'cli' && !isset($update)) {
    $stats = get_stats();
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    echo "<!DOCTYPE html>";
    echo "<html><head><title>Entertainment Tadka Bot</title>";
    echo "<style>body{font-family:Arial;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5}.card{background:white;padding:20px;border-radius:10px;box-shadow:0 2px 5px rgba(0,0,0,0.1)}h1{color:#333}.status{color:green;font-weight:bold}</style>";
    echo "</head><body>";
    echo "<div class='card'>";
    echo "<h1>🎬 Entertainment Tadka Bot</h1>";
    echo "<p class='status'>✅ Bot is running</p>";
    echo "<hr>";
    
    echo "<h3>📊 Statistics</h3>";
    echo "<ul>";
    echo "<li>🎬 Movies: " . ($stats['total_movies'] ?? 0) . "</li>";
    echo "<li>👥 Users: " . count($users_data['users'] ?? []) . "</li>";
    echo "<li>🔍 Searches: " . ($stats['total_searches'] ?? 0) . "</li>";
    echo "<li>📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "</li>";
    echo "</ul>";
    
    echo "<h3>📢 Channels</h3>";
    echo "<ul>";
    echo "<li>🍿 Main: @EntertainmentTadka786</li>";
    echo "<li>📺 Serials: @Entertainment_Tadka_Serial_786</li>";
    echo "<li>🎭 Theater: @threater_print_movies</li>";
    echo "<li>🔒 Backup: @ETBackup</li>";
    echo "<li>📥 Requests: @EntertainmentTadka7860</li>";
    echo "</ul>";
    
    echo "<h3>🔧 Setup</h3>";
    echo "<p><a href='?setwebhook=1' style='background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px'>Set Webhook</a></p>";
    
    echo "<p><small>Last updated: " . date('Y-m-d H:i:s') . "</small></p>";
    echo "</div>";
    echo "</body></html>";
}

// ==============================
// WEBHOOK SETUP HANDLER
// ==============================
if (isset($_GET['setwebhook'])) {
    $webhook_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $result = apiRequest('setWebhook', ['url' => $webhook_url]);
    
    echo "<h2>Webhook Setup</h2>";
    echo "<pre>" . htmlspecialchars($result) . "</pre>";
    echo "<p>Webhook URL: " . htmlspecialchars($webhook_url) . "</p>";
    echo "<p><a href='?'>Back to Home</a></p>";
    exit;
}
?>
