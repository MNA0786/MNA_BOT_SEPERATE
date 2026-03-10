<?php
// ==============================
// COMMAND HANDLER
// ==============================

require_once 'config.php';
require_once 'database.php';
require_once 'telegram.php';
require_once 'movies.php';
require_once 'users.php';
require_once 'channels.php';
require_once 'admin.php';
require_once 'backup.php';
require_once 'settings.php';
require_once 'auto_scan.php';
require_once 'pagination.php';

function handle_command($chat_id, $user_id, $command, $params) {
    $cmd = strtolower($command);
    
    // ==================== CORE COMMANDS ====================
    if ($cmd == '/start' || $cmd == '/start@entertainmenttadkabot') {
        $welcome = "🎬 <b>Welcome to Entertainment Tadka Bot!</b>\n\n";
        $welcome .= "📢 <b>How to use:</b>\n";
        $welcome .= "• Just type any movie name\n";
        $welcome .= "• Examples: 'kgf 2', 'avengers', 'pushpa'\n";
        $welcome .= "• Add 'theater' for theater prints\n\n";
        
        $welcome .= "📢 <b>Our Channels:</b>\n";
        $welcome .= "🍿 Main: @EntertainmentTadka786\n";
        $welcome .= "📺 Serials: @Entertainment_Tadka_Serial_786\n";
        $welcome .= "🎭 Theater: @threater_print_movies\n";
        $welcome .= "🔒 Backup: @ETBackup\n";
        $welcome .= "📥 Requests: @EntertainmentTadka7860\n\n";
        
        $welcome .= "💬 Need help? Use /help";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔍 Search', 'switch_inline_query_current_chat' => ''],
                    ['text' => '🍿 Main Channel', 'url' => 'https://t.me/EntertainmentTadka786']
                ],
                [
                    ['text' => '📥 Request', 'callback_data' => 'request_movie'],
                    ['text' => '⚙️ Settings', 'callback_data' => 'open_settings']
                ]
            ]
        ];
        
        sendMessage($chat_id, $welcome, $keyboard, 'HTML');
        update_user_activity($user_id, 'daily_login');
    }
    
    elseif ($cmd == '/help' || $cmd == '/commands') {
        $help = "🤖 <b>Entertainment Tadka Bot - Commands</b>\n\n";
        
        $help .= "🔍 <b>Search:</b>\n";
        $help .= "• <code>/search movie</code> - Search movies\n";
        $help .= "• <code>/s movie</code> - Quick search\n";
        $help .= "• Just type movie name - Auto-search\n\n";
        
        $help .= "📁 <b>Browse:</b>\n";
        $help .= "• <code>/totalupload</code> - All movies\n";
        $help .= "• <code>/latest</code> - Latest additions\n";
        $help .= "• <code>/theater</code> - Theater prints only\n\n";
        
        $help .= "📝 <b>Requests:</b>\n";
        $help .= "• <code>/request movie</code> - Request movie\n";
        $help .= "• <code>/myrequests</code> - Your requests\n\n";
        
        $help .= "👤 <b>User:</b>\n";
        $help .= "• <code>/mystats</code> - Your statistics\n";
        $help .= "• <code>/leaderboard</code> - Top users\n";
        $help .= "• <code>/settings</code> - Preferences\n\n";
        
        $help .= "📢 <b>Channels:</b>\n";
        $help .= "• <code>/channels</code> - All channels\n";
        $help .= "• <code>/main</code> - Main channel\n";
        $help .= "• <code>/theater</code> - Theater channel\n\n";
        
        $help .= "ℹ️ <b>Info:</b>\n";
        $help .= "• <code>/info</code> - Bot info\n";
        $help .= "• <code>/ping</code> - Check status";
        
        if ($user_id == ADMIN_ID) {
            $help .= "\n\n👑 <b>Admin Commands:</b>\n";
            $help .= "• <code>/stats</code> - Bot statistics\n";
            $help .= "• <code>/broadcast msg</code> - Send to all\n";
            $help .= "• <code>/backup</code> - Manual backup\n";
            $help .= "• <code>/scan</code> - Scan channels\n";
            $help .= "• <code>/cleanup</code> - System cleanup\n";
            $help .= "• <code>/quickadd</code> - Quick add movie";
        }
        
        sendMessage($chat_id, $help, null, 'HTML');
    }
    
    // ==================== SEARCH COMMANDS ====================
    elseif ($cmd == '/search' || $cmd == '/s' || $cmd == '/find') {
        $query = implode(' ', $params);
        if (empty($query)) {
            sendMessage($chat_id, "❌ Usage: /search movie name");
            return;
        }
        advanced_search($chat_id, $query, $user_id);
    }
    
    // ==================== BROWSE COMMANDS ====================
    elseif ($cmd == '/totalupload' || $cmd == '/allmovies' || $cmd == '/browse') {
        $page = isset($params[0]) ? intval($params[0]) : 1;
        totalupload_controller($chat_id, $page);
    }
    
    elseif ($cmd == '/latest' || $cmd == '/new') {
        $limit = isset($params[0]) ? intval($params[0]) : 10;
        show_latest_movies($chat_id, $limit);
    }
    
    elseif ($cmd == '/theater' || $cmd == '/theateronly') {
        $all = get_all_movies_list();
        $filtered = apply_filters($all, ['channel_type' => 'theater']);
        if (empty($filtered)) {
            sendMessage($chat_id, "❌ No theater movies found!");
        } else {
            batchSendWithETA($chat_id, array_slice($filtered, 0, 20), "Theater Movies");
        }
    }
    
    // ==================== CHANNEL COMMANDS ====================
    elseif ($cmd == '/channels' || $cmd == '/join') {
        show_channel_info($chat_id, $user_id);
    }
    
    elseif ($cmd == '/main' || $cmd == '/mainchannel') {
        show_main_channel_info($chat_id);
    }
    
    elseif ($cmd == '/serial' || $cmd == '/serialchannel') {
        show_serial_channel_info($chat_id);
    }
    
    elseif ($cmd == '/theaterchannel') {
        show_theater_channel_info($chat_id);
    }
    
    elseif ($cmd == '/backupchannel') {
        show_backup_channel_info($chat_id, $user_id);
    }
    
    elseif ($cmd == '/requestchannel' || $cmd == '/support') {
        show_request_group_info($chat_id);
    }
    
    // ==================== REQUEST COMMANDS ====================
    elseif ($cmd == '/request' || $cmd == '/req') {
        $movie = implode(' ', $params);
        if (empty($movie)) {
            sendMessage($chat_id, "❌ Usage: /request movie name");
            return;
        }
        
        if (add_movie_request($user_id, $movie)) {
            sendMessage($chat_id, "✅ Request submitted! We'll add it soon.");
        } else {
            sendMessage($chat_id, "❌ Daily limit reached! (" . DAILY_REQUEST_LIMIT . "/day)");
        }
    }
    
    elseif ($cmd == '/myrequests') {
        show_user_requests($chat_id, $user_id);
    }
    
    // ==================== USER COMMANDS ====================
    elseif ($cmd == '/mystats' || $cmd == '/profile') {
        show_user_stats($chat_id, $user_id);
    }
    
    elseif ($cmd == '/leaderboard' || $cmd == '/topusers') {
        show_leaderboard($chat_id);
    }
    
    elseif ($cmd == '/settings' || $cmd == '/preferences') {
        show_settings_panel($chat_id, $user_id);
    }
    
    // ==================== INFO COMMANDS ====================
    elseif ($cmd == '/info' || $cmd == '/about') {
        $stats = get_stats();
        $users_data = json_decode(file_get_contents(USERS_FILE), true);
        
        $msg = "🤖 <b>Entertainment Tadka Bot</b>\n\n";
        $msg .= "📊 <b>Statistics:</b>\n";
        $msg .= "• 🎬 Movies: " . ($stats['total_movies'] ?? 0) . "\n";
        $msg .= "• 👥 Users: " . count($users_data['users'] ?? []) . "\n";
        $msg .= "• 🔍 Searches: " . ($stats['total_searches'] ?? 0) . "\n";
        $msg .= "• 📥 Downloads: " . ($stats['total_downloads'] ?? 0) . "\n\n";
        
        $msg .= "📅 Last Updated: " . ($stats['last_updated'] ?? 'N/A') . "\n";
        $msg .= "⚡ Status: 🟢 Online";
        
        sendMessage($chat_id, $msg, null, 'HTML');
    }
    
    elseif ($cmd == '/ping') {
        sendMessage($chat_id, "🏓 Pong!\n⏱️ " . date('Y-m-d H:i:s'));
    }
    
    // ==================== ADMIN COMMANDS ====================
    elseif ($cmd == '/stats' && $user_id == ADMIN_ID) {
        admin_stats($chat_id, $user_id);
    }
    
    elseif ($cmd == '/broadcast' && $user_id == ADMIN_ID) {
        $message = implode(' ', $params);
        send_broadcast($chat_id, $user_id, $message);
    }
    
    elseif ($cmd == '/backup' && $user_id == ADMIN_ID) {
        manual_backup($chat_id, $user_id);
    }
    
    elseif ($cmd == '/scan' && $user_id == ADMIN_ID) {
        sendMessage($chat_id, "🔍 Scanning channels...");
        $new = scan_all_channels();
        sendMessage($chat_id, "✅ Scan complete!");
    }
    
    elseif ($cmd == '/cleanup' && $user_id == ADMIN_ID) {
        perform_cleanup($chat_id, $user_id);
    }
    
    elseif ($cmd == '/quickadd' && $user_id == ADMIN_ID) {
        $input = implode(' ', $params);
        quick_add_movie($chat_id, $user_id, $input);
    }
    
    elseif ($cmd == '/checkcsv' && $user_id == ADMIN_ID) {
        $show_all = isset($params[0]) && $params[0] == 'all';
        show_csv_data($chat_id, $user_id, $show_all);
    }
    
    // ==================== DEFAULT ====================
    else {
        sendMessage($chat_id, "❌ Unknown command. Use /help for available commands.");
    }
}
?>