<?php
// ==============================
// CALLBACK QUERY HANDLER
// ==============================

require_once 'config.php';
require_once 'database.php';
require_once 'telegram.php';
require_once 'movies.php';
require_once 'pagination.php';
require_once 'bulk_send.php';
require_once 'settings.php';
require_once 'file_delete.php';
require_once 'admin.php';
require_once 'users.php';

function handle_callback_query($callback_query) {
    $query_id = $callback_query['id'];
    $user_id = $callback_query['from']['id'];
    $chat_id = $callback_query['message']['chat']['id'];
    $message_id = $callback_query['message']['message_id'];
    $data = $callback_query['data'];
    
    global $movie_messages, $delete_timers;
    
    // ==================== MOVIE SELECTION ====================
    if (strpos($data, 'movie_') === 0) {
        $movie_name = base64_decode(substr($data, 6));
        show_movie_versions_pagination($chat_id, $movie_name);
        answerCallbackQuery($query_id, "🎬 Showing versions...");
    }
    
    // ==================== VERSION PAGINATION ====================
    elseif (strpos($data, 'ver_prev_') === 0) {
        $parts = explode('_', $data);
        $movie_encoded = $parts[2];
        $current_page = intval($parts[3]);
        $movie_name = base64_decode($movie_encoded);
        show_movie_versions_pagination($chat_id, $movie_name, $current_page - 1);
        answerCallbackQuery($query_id, "◀️ Previous page");
    }
    
    elseif (strpos($data, 'ver_next_') === 0) {
        $parts = explode('_', $data);
        $movie_encoded = $parts[2];
        $current_page = intval($parts[3]);
        $movie_name = base64_decode($movie_encoded);
        show_movie_versions_pagination($chat_id, $movie_name, $current_page + 1);
        answerCallbackQuery($query_id, "▶️ Next page");
    }
    
    elseif (strpos($data, 'ver_select_') === 0) {
        $parts = explode('_', $data);
        $movie_encoded = $parts[2];
        $version_num = intval($parts[3]);
        $movie_name = base64_decode($movie_encoded);
        
        $movie_key = strtolower($movie_name);
        if (isset($movie_messages[$movie_key]) && isset($movie_messages[$movie_key][$version_num - 1])) {
            $item = $movie_messages[$movie_key][$version_num - 1];
            deliver_item_to_chat($chat_id, $item);
            answerCallbackQuery($query_id, "✅ Version {$version_num} sent!");
        } else {
            answerCallbackQuery($query_id, "❌ Version not found", true);
        }
    }
    
    elseif ($data == 'ver_send_all_') {
        $movie_encoded = substr($data, 13);
        $movie_name = base64_decode($movie_encoded);
        $movie_key = strtolower($movie_name);
        
        if (isset($movie_messages[$movie_key])) {
            $versions = $movie_messages[$movie_key];
            batchSendWithETA($chat_id, $versions, "Sending all versions of {$movie_name}");
            answerCallbackQuery($query_id, "📦 Sending all versions...");
        }
    }
    
    // ==================== PAGINATION ====================
    elseif (strpos($data, 'pag_first_') === 0) {
        $session_id = substr($data, 10);
        totalupload_controller($chat_id, 1, [], $session_id);
        answerCallbackQuery($query_id, "⏮️ First page");
    }
    
    elseif (strpos($data, 'pag_last_') === 0) {
        $parts = explode('_', $data);
        $total_pages = intval($parts[2]);
        $session_id = $parts[3] ?? '';
        totalupload_controller($chat_id, $total_pages, [], $session_id);
        answerCallbackQuery($query_id, "⏭️ Last page");
    }
    
    elseif (strpos($data, 'pag_prev_') === 0) {
        $parts = explode('_', $data);
        $current = intval($parts[2]);
        $session_id = $parts[3] ?? '';
        totalupload_controller($chat_id, max(1, $current - 1), [], $session_id);
        answerCallbackQuery($query_id, "◀️ Previous page");
    }
    
    elseif (strpos($data, 'pag_next_') === 0) {
        $parts = explode('_', $data);
        $current = intval($parts[2]);
        $session_id = $parts[3] ?? '';
        totalupload_controller($chat_id, $current + 1, [], $session_id);
        answerCallbackQuery($query_id, "▶️ Next page");
    }
    
    elseif (strpos($data, 'pag_') === 0 && is_numeric(explode('_', $data)[1])) {
        $parts = explode('_', $data);
        $page = intval($parts[1]);
        $session_id = $parts[2] ?? '';
        totalupload_controller($chat_id, $page, [], $session_id);
        answerCallbackQuery($query_id, "📄 Page {$page}");
    }
    
    // ==================== SEND PAGE ====================
    elseif (strpos($data, 'send_page_') === 0) {
        $parts = explode('_', $data);
        $page = intval($parts[2]);
        $session_id = $parts[3] ?? '';
        
        $all = get_all_movies_list();
        $pg = paginate_movies($all, $page, []);
        batchSendWithETA($chat_id, $pg['slice'], "Page {$page} Movies");
        answerCallbackQuery($query_id, "📥 Sending page {$page}...");
    }
    
    // ==================== BULK SEND ====================
    elseif (strpos($data, 'bulk_send_page_') === 0) {
        $parts = explode('_', $data);
        $page = intval($parts[3]);
        $session_id = $parts[4] ?? '';
        bulk_send_page($chat_id, $page, $session_id);
        answerCallbackQuery($query_id, "📦 Sending page {$page}...");
    }
    
    elseif (strpos($data, 'bulk_send_all_') === 0) {
        bulk_send_all_pages($chat_id);
        answerCallbackQuery($query_id, "📦 Sending all movies...");
    }
    
    elseif ($data == 'confirm_bulk_all') {
        $all = get_all_movies_list();
        batchSendWithETA($chat_id, $all, "All Movies");
        answerCallbackQuery($query_id, "📦 Started...");
    }
    
    // ==================== FILTERS ====================
    elseif (strpos($data, 'filter_') === 0) {
        $parts = explode('_', $data);
        $filter = $parts[1];
        $session_id = $parts[2] ?? '';
        
        $filters = [];
        switch ($filter) {
            case 'hd': $filters = ['quality' => '1080p']; break;
            case 'theater': $filters = ['channel_type' => 'theater']; break;
            case 'hindi': $filters = ['language' => 'Hindi']; break;
            case 'english': $filters = ['language' => 'English']; break;
            case 'clear': $filters = []; break;
        }
        
        totalupload_controller($chat_id, 1, $filters, $session_id);
        answerCallbackQuery($query_id, "🔍 Filter applied");
    }
    
    // ==================== PREVIEW & STATS ====================
    elseif (strpos($data, 'preview_page_') === 0) {
        $parts = explode('_', $data);
        $page = intval($parts[2]);
        $session_id = $parts[3] ?? '';
        show_page_preview($chat_id, $page, $session_id);
        answerCallbackQuery($query_id, "👁️ Preview sent");
    }
    
    elseif (strpos($data, 'stats_page_') === 0) {
        $parts = explode('_', $data);
        $page = intval($parts[2]);
        $session_id = $parts[3] ?? '';
        show_page_stats($chat_id, $page, $session_id);
        answerCallbackQuery($query_id, "📊 Stats sent");
    }
    
    // ==================== REFRESH ====================
    elseif (strpos($data, 'refresh_page_') === 0) {
        $parts = explode('_', $data);
        $page = intval($parts[2]);
        $session_id = $parts[3] ?? '';
        totalupload_controller($chat_id, $page, [], $session_id);
        answerCallbackQuery($query_id, "🔄 Refreshed");
    }
    
    // ==================== CLOSE PAGINATION ====================
    elseif (strpos($data, 'close_pagination_') === 0) {
        deleteMessage($chat_id, $message_id);
        answerCallbackQuery($query_id, "🗂️ Closed");
    }
    
    // ==================== AUTO-DELETE TIMER ====================
    elseif (strpos($data, 'cancel_delete_') === 0) {
        $timer_id = substr($data, 14);
        if (cancel_deletion($chat_id, $timer_id)) {
            editMessage($chat_id, $message_id, "✅ Auto-delete cancelled!", null);
            answerCallbackQuery($query_id, "✅ Cancelled");
        } else {
            answerCallbackQuery($query_id, "❌ Timer not found", true);
        }
    }
    
    elseif (strpos($data, 'delete_now_') === 0) {
        $target_id = intval(substr($data, 11));
        deleteMessage($chat_id, $target_id);
        deleteMessage($chat_id, $message_id);
        answerCallbackQuery($query_id, "🗑️ Deleted");
    }
    
    elseif (strpos($data, 'show_timer_') === 0) {
        $timer_id = substr($data, 11);
        if (isset($delete_timers[$timer_id])) {
            $timer = $delete_timers[$timer_id];
            $remaining = $timer['delete_time'] - time();
            $time_str = format_time_eta($remaining);
            answerCallbackQuery($query_id, "⏱️ Time remaining: {$time_str}");
        }
    }
    
    // ==================== SETTINGS ====================
    elseif ($data == 'open_settings') {
        show_settings_panel($chat_id, $user_id);
        answerCallbackQuery($query_id, "⚙️ Settings");
    }
    
    elseif (strpos($data, 'settings_') === 0 || strpos($data, 'timer_') === 0) {
        handle_settings_callback($chat_id, $user_id, $data, $message_id);
        answerCallbackQuery($query_id, "⚙️ Updated");
    }
    
    elseif ($data == 'close_settings') {
        deleteMessage($chat_id, $message_id);
        answerCallbackQuery($query_id, "❌ Closed");
    }
    
    // ==================== USER STATS ====================
    elseif ($data == 'show_leaderboard') {
        show_leaderboard($chat_id);
        answerCallbackQuery($query_id, "📈 Leaderboard");
    }
    
    // ==================== AUTO REQUESTS ====================
    elseif (strpos($data, 'auto_request_') === 0) {
        $movie_name = base64_decode(substr($data, 13));
        $lang = 'hindi'; // Default
        
        if (add_movie_request($user_id, $movie_name, $lang)) {
            answerCallbackQuery($query_id, "✅ Request sent!");
            sendMessage($chat_id, "✅ Request received for: {$movie_name}");
        } else {
            answerCallbackQuery($query_id, "❌ Daily limit reached!", true);
        }
    }
    
    elseif ($data == 'request_movie') {
        sendMessage($chat_id, "📝 Use /request movie_name to request movies.");
        answerCallbackQuery($query_id, "📝 Request help");
    }
    
    // ==================== ADMIN BROADCAST ====================
    elseif (strpos($data, 'confirm_broadcast_') === 0) {
        if ($user_id == ADMIN_ID) {
            $message_encoded = substr($data, 18);
            execute_broadcast($chat_id, $user_id, $message_encoded);
            deleteMessage($chat_id, $message_id);
        } else {
            answerCallbackQuery($query_id, "❌ Admin only", true);
        }
    }
    
    elseif ($data == 'cancel_broadcast') {
        deleteMessage($chat_id, $message_id);
        sendMessage($chat_id, "✅ Broadcast cancelled.");
        answerCallbackQuery($query_id, "Cancelled");
    }
    
    // ==================== HELP ====================
    elseif ($data == 'help_command') {
        $help = "🤖 <b>Bot Commands</b>\n\n";
        $help .= "• /start - Welcome\n";
        $help .= "• /help - This menu\n";
        $help .= "• /search movie - Search\n";
        $help .= "• /totalupload - Browse all\n";
        $help .= "• /latest - Latest movies\n";
        $help .= "• /request movie - Request\n";
        $help .= "• /mystats - Your stats\n";
        $help .= "• /leaderboard - Top users\n";
        $help .= "• /settings - Preferences";
        
        sendMessage($chat_id, $help, null, 'HTML');
        answerCallbackQuery($query_id, "❓ Help");
    }
    
    // ==================== CURRENT PAGE ====================
    elseif ($data == 'current_page') {
        answerCallbackQuery($query_id, "You are on this page");
    }
    
    // ==================== DEFAULT ====================
    else {
        answerCallbackQuery($query_id, "❓ Unknown command");
    }
}
?>