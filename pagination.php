<?php
// ==============================
// ENHANCED PAGINATION SYSTEM
// ==============================

require_once 'config.php';
require_once 'database.php';
require_once 'channels.php';
require_once 'telegram.php';
require_once 'bulk_send.php';

$user_pagination_sessions = [];

function paginate_movies($all, $page, $filters = []) {
    if (!empty($filters)) {
        $all = apply_filters($all, $filters);
    }
    
    $total = count($all);
    if ($total === 0) {
        return [
            'total' => 0, 
            'total_pages' => 1, 
            'page' => 1, 
            'slice' => [],
            'filters' => $filters,
            'has_next' => false,
            'has_prev' => false,
            'start_item' => 0,
            'end_item' => 0
        ];
    }
    
    $total_pages = ceil($total / ITEMS_PER_PAGE);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * ITEMS_PER_PAGE;
    
    return [
        'total' => $total,
        'total_pages' => $total_pages,
        'page' => $page,
        'slice' => array_slice($all, $start, ITEMS_PER_PAGE),
        'filters' => $filters,
        'has_next' => $page < $total_pages,
        'has_prev' => $page > 1,
        'start_item' => $start + 1,
        'end_item' => min($start + ITEMS_PER_PAGE, $total)
    ];
}

function apply_filters($movies, $filters) {
    $filtered = [];
    foreach ($movies as $movie) {
        $pass = true;
        foreach ($filters as $key => $value) {
            switch ($key) {
                case 'quality':
                    if (stripos($movie['quality'] ?? '', $value) === false) $pass = false;
                    break;
                case 'language':
                    if (stripos($movie['language'] ?? '', $value) === false) $pass = false;
                    break;
                case 'channel_type':
                    if (($movie['channel_type'] ?? '') != $value) $pass = false;
                    break;
                case 'year':
                    $movie_year = substr($movie['date'] ?? '', -4);
                    if ($movie_year != $value) $pass = false;
                    break;
            }
            if (!$pass) break;
        }
        if ($pass) $filtered[] = $movie;
    }
    return $filtered;
}

function totalupload_controller($chat_id, $page = 1, $filters = [], $session_id = null) {
    global $user_pagination_sessions;
    
    $all = get_all_movies_list();
    if (empty($all)) {
        sendMessage($chat_id, "📭 No movies found! Please add some movies first.");
        return;
    }
    
    if (!$session_id) {
        $session_id = uniqid('page_', true);
    }
    
    $pg = paginate_movies($all, $page, $filters);
    
    // Build message with preview
    $title = "🎬 <b>Movie Browser</b>\n\n";
    $title .= "📊 <b>Statistics:</b>\n";
    $title .= "• Total Movies: <b>{$pg['total']}</b>\n";
    $title .= "• Page: <b>{$pg['page']}/{$pg['total_pages']}</b>\n";
    $title .= "• Items: <b>{$pg['start_item']}-{$pg['end_item']}</b>\n";
    
    if (!empty($filters)) {
        $title .= "• Active Filters: <b>" . count($filters) . "</b>\n";
    }
    
    $title .= "\n📋 <b>Movies on this page:</b>\n\n";
    
    // List movies with details
    foreach ($pg['slice'] as $index => $movie) {
        $num = $pg['start_item'] + $index;
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main', $chat_id);
        $quality = $movie['quality'] ?? 'Unknown';
        $language = $movie['language'] ?? 'Hindi';
        
        $title .= "<b>{$num}.</b> {$channel_icon} " . htmlspecialchars($movie['movie_name']) . "\n";
        $title .= "   🏷️ {$quality} | 🗣️ {$language}";
        
        if (!empty($movie['size']) && $movie['size'] != 'Unknown') {
            $title .= " | 💾 {$movie['size']}";
        }
        
        $title .= "\n\n";
        
        // Prevent message too long
        if (strlen($title) > 3500) {
            $title .= "... (more movies on next page)\n\n";
            break;
        }
    }
    
    $title .= "📍 <i>Use buttons below to navigate or send</i>";
    
    $keyboard = build_pagination_keyboard($pg, $session_id);
    
    // Delete previous message if exists
    delete_pagination_message($chat_id, $session_id);
    
    // Send new message
    $result = sendMessage($chat_id, $title, $keyboard, 'HTML');
    
    if ($result && isset($result['result']['message_id'])) {
        save_pagination_message($chat_id, $session_id, $result['result']['message_id'], $pg['page']);
    }
}

function build_pagination_keyboard($pg, $session_id) {
    $kb = ['inline_keyboard' => []];
    $page = $pg['page'];
    $total_pages = $pg['total_pages'];
    $has_filters = !empty($pg['filters']);
    
    // ==================== NAVIGATION ROW ====================
    $nav_row = [];
    
    // First page button
    if ($page > 1) {
        $nav_row[] = ['text' => '⏮️ First', 'callback_data' => 'pag_first_' . $session_id];
    }
    
    // Previous page button
    if ($pg['has_prev']) {
        $nav_row[] = ['text' => '◀️ Prev', 'callback_data' => 'pag_prev_' . $page . '_' . $session_id];
    }
    
    // Current page indicator
    $nav_row[] = ['text' => "📄 {$page}/{$total_pages}", 'callback_data' => 'current_page'];
    
    // Next page button
    if ($pg['has_next']) {
        $nav_row[] = ['text' => 'Next ▶️', 'callback_data' => 'pag_next_' . $page . '_' . $session_id];
    }
    
    // Last page button
    if ($page < $total_pages) {
        $nav_row[] = ['text' => 'Last ⏭️', 'callback_data' => 'pag_last_' . $total_pages . '_' . $session_id];
    }
    
    if (!empty($nav_row)) {
        $kb['inline_keyboard'][] = $nav_row;
    }
    
    // ==================== PAGE NUMBER ROW ====================
    $page_row = [];
    $start_page = max(1, $page - 3);
    $end_page = min($total_pages, $start_page + 6);
    
    if ($end_page - $start_page < 6) {
        $start_page = max(1, $end_page - 6);
    }
    
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $page) {
            $page_row[] = ['text' => "▪️{$i}▪️", 'callback_data' => 'current_page'];
        } else {
            $page_row[] = ['text' => "{$i}", 'callback_data' => 'pag_' . $i . '_' . $session_id];
        }
    }
    
    if (!empty($page_row)) {
        $kb['inline_keyboard'][] = $page_row;
    }
    
    // ==================== ACTION ROW ====================
    $action_row = [];
    $action_row[] = ['text' => '📥 Send This Page', 'callback_data' => 'send_page_' . $page . '_' . $session_id];
    $action_row[] = ['text' => '📦 SEND ALL', 'callback_data' => 'bulk_send_page_' . $page . '_' . $session_id];
    $kb['inline_keyboard'][] = $action_row;
    
    // ==================== FILTER ROW ====================
    if (!$has_filters) {
        $filter_row = [];
        $filter_row[] = ['text' => '🎬 HD Only (1080p)', 'callback_data' => 'filter_hd_' . $session_id];
        $filter_row[] = ['text' => '🎭 Theater Only', 'callback_data' => 'filter_theater_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row;
        
        $filter_row2 = [];
        $filter_row2[] = ['text' => '🗣️ Hindi', 'callback_data' => 'filter_hindi_' . $session_id];
        $filter_row2[] = ['text' => '🔤 English', 'callback_data' => 'filter_english_' . $session_id];
        $kb['inline_keyboard'][] = $filter_row2;
    } else {
        $clear_row = [];
        $clear_row[] = ['text' => '🧹 Clear Filters', 'callback_data' => 'filter_clear_' . $session_id];
        $kb['inline_keyboard'][] = $clear_row;
    }
    
    // ==================== PREVIEW ROW ====================
    $preview_row = [];
    $preview_row[] = ['text' => '👁️ Preview First 3', 'callback_data' => 'preview_page_' . $page . '_' . $session_id];
    $preview_row[] = ['text' => '📊 Page Stats', 'callback_data' => 'stats_page_' . $page . '_' . $session_id];
    $kb['inline_keyboard'][] = $preview_row;
    
    // ==================== CONTROL ROW ====================
    $ctrl_row = [];
    $ctrl_row[] = ['text' => '💾 Save Session', 'callback_data' => 'save_session_' . $session_id];
    $ctrl_row[] = ['text' => '🔄 Refresh', 'callback_data' => 'refresh_page_' . $page . '_' . $session_id];
    $ctrl_row[] = ['text' => '❌ Close', 'callback_data' => 'close_pagination_' . $session_id];
    $kb['inline_keyboard'][] = $ctrl_row;
    
    return $kb;
}

// ==============================
// MULTIPLE MOVIE VERSIONS PAGINATION
// ==============================

function show_movie_versions_pagination($chat_id, $movie_name, $page = 1) {
    global $movie_messages;
    
    $movie_key = strtolower($movie_name);
    if (!isset($movie_messages[$movie_key]) || empty($movie_messages[$movie_key])) {
        sendMessage($chat_id, "❌ No versions found for this movie!");
        return;
    }
    
    $versions = $movie_messages[$movie_key];
    $total_versions = count($versions);
    $total_pages = ceil($total_versions / ITEMS_PER_PAGE);
    $page = max(1, min($page, $total_pages));
    $start = ($page - 1) * ITEMS_PER_PAGE;
    $page_versions = array_slice($versions, $start, ITEMS_PER_PAGE);
    
    $message = "🎬 <b>" . htmlspecialchars($movie_name) . "</b>\n";
    $message .= "📦 Total Versions: <b>{$total_versions}</b>\n";
    $message .= "📄 Page: <b>{$page}/{$total_pages}</b>\n\n";
    
    $message .= "📋 <b>Available Versions:</b>\n\n";
    
    foreach ($page_versions as $index => $version) {
        $num = $start + $index + 1;
        $channel_icon = get_channel_display_name($version['channel_type'] ?? 'main', $chat_id);
        $quality = $version['quality'] ?? 'Unknown';
        $language = $version['language'] ?? 'Hindi';
        $size = $version['size'] ?? 'Unknown';
        $date = $version['date'] ?? 'N/A';
        
        $message .= "<b>{$num}.</b> {$channel_icon}\n";
        $message .= "   🏷️ Quality: <b>{$quality}</b>\n";
        $message .= "   🗣️ Language: <b>{$language}</b>\n";
        $message .= "   💾 Size: <b>{$size}</b>\n";
        $message .= "   📅 Added: <b>{$date}</b>\n\n";
    }
    
    $keyboard = build_version_keyboard($movie_name, $page, $total_pages, $total_versions);
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function build_version_keyboard($movie_name, $current_page, $total_pages, $total_versions) {
    $kb = ['inline_keyboard' => []];
    $movie_encoded = base64_encode($movie_name);
    
    // Navigation for versions
    if ($total_pages > 1) {
        $nav_row = [];
        
        if ($current_page > 1) {
            $nav_row[] = ['text' => '◀️ Prev', 'callback_data' => 'ver_prev_' . $movie_encoded . '_' . $current_page];
        }
        
        $nav_row[] = ['text' => "📄 {$current_page}/{$total_pages}", 'callback_data' => 'current_page'];
        
        if ($current_page < $total_pages) {
            $nav_row[] = ['text' => 'Next ▶️', 'callback_data' => 'ver_next_' . $movie_encoded . '_' . $current_page];
        }
        
        $kb['inline_keyboard'][] = $nav_row;
    }
    
    // Version selection buttons
    $select_row = [];
    $start = ($current_page - 1) * ITEMS_PER_PAGE;
    
    for ($i = 0; $i < min(ITEMS_PER_PAGE, $total_versions - $start); $i++) {
        $version_num = $start + $i + 1;
        $select_row[] = ['text' => "Version {$version_num}", 'callback_data' => 'ver_select_' . $movie_encoded . '_' . $version_num];
        
        if (($i + 1) % 3 == 0) {
            $kb['inline_keyboard'][] = $select_row;
            $select_row = [];
        }
    }
    
    if (!empty($select_row)) {
        $kb['inline_keyboard'][] = $select_row;
    }
    
    // Action buttons
    $action_row = [
        ['text' => '📥 Send All Versions', 'callback_data' => 'ver_send_all_' . $movie_encoded],
        ['text' => '❌ Close', 'callback_data' => 'close_versions']
    ];
    $kb['inline_keyboard'][] = $action_row;
    
    return $kb;
}

// ==============================
// PAGINATION HELPER FUNCTIONS
// ==============================

function save_pagination_message($chat_id, $session_id, $message_id, $page = 1) {
    global $user_pagination_sessions;
    
    $user_pagination_sessions[$session_id] = [
        'last_message_id' => $message_id,
        'chat_id' => $chat_id,
        'last_updated' => time(),
        'current_page' => $page
    ];
}

function delete_pagination_message($chat_id, $session_id) {
    global $user_pagination_sessions;
    
    if (isset($user_pagination_sessions[$session_id]) && 
        isset($user_pagination_sessions[$session_id]['last_message_id'])) {
        
        $message_id = $user_pagination_sessions[$session_id]['last_message_id'];
        @deleteMessage($chat_id, $message_id);
    }
}

function get_pagination_session($session_id) {
    global $user_pagination_sessions;
    return $user_pagination_sessions[$session_id] ?? null;
}

// ==============================
// PREVIEW FUNCTIONS
// ==============================

function show_page_preview($chat_id, $page, $session_id) {
    $all = get_all_movies_list();
    $pg = paginate_movies($all, $page, []);
    
    $preview = "👁️ <b>Page {$page} Preview</b>\n\n";
    $preview .= "📊 First 3 movies on this page:\n\n";
    
    $limit = min(3, count($pg['slice']));
    for ($i = 0; $i < $limit; $i++) {
        $movie = $pg['slice'][$i];
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main', $chat_id);
        $preview .= ($i + 1) . ". {$channel_icon} <b>" . htmlspecialchars($movie['movie_name']) . "</b>\n";
        $preview .= "   🏷️ {$movie['quality']} | 🗣️ {$movie['language']}\n\n";
    }
    
    if (count($pg['slice']) > 3) {
        $preview .= "... and " . (count($pg['slice']) - 3) . " more movies\n";
    }
    
    sendMessage($chat_id, $preview, null, 'HTML');
}

function show_page_stats($chat_id, $page, $session_id) {
    $all = get_all_movies_list();
    $pg = paginate_movies($all, $page, []);
    
    // Calculate stats for this page
    $qualities = [];
    $languages = [];
    $channel_types = [];
    
    foreach ($pg['slice'] as $movie) {
        $q = $movie['quality'] ?? 'Unknown';
        $qualities[$q] = ($qualities[$q] ?? 0) + 1;
        
        $lang = $movie['language'] ?? 'Hindi';
        $languages[$lang] = ($languages[$lang] ?? 0) + 1;
        
        $ch = $movie['channel_type'] ?? 'main';
        $channel_types[$ch] = ($channel_types[$ch] ?? 0) + 1;
    }
    
    $stats = "📊 <b>Page {$page} Statistics</b>\n\n";
    $stats .= "📄 Page: {$pg['page']}/{$pg['total_pages']}\n";
    $stats .= "🎬 Movies on this page: " . count($pg['slice']) . "\n\n";
    
    $stats .= "🏷️ <b>Qualities:</b>\n";
    foreach ($qualities as $q => $count) {
        $stats .= "• {$q}: {$count}\n";
    }
    
    $stats .= "\n🗣️ <b>Languages:</b>\n";
    foreach ($languages as $lang => $count) {
        $stats .= "• {$lang}: {$count}\n";
    }
    
    $stats .= "\n🎭 <b>Channels:</b>\n";
    foreach ($channel_types as $ch => $count) {
        $channel_name = get_channel_display_name($ch, $chat_id);
        $stats .= "• {$channel_name}: {$count}\n";
    }
    
    sendMessage($chat_id, $stats, null, 'HTML');
}

// ==============================
// FILTER HANDLING
// ==============================

function handle_filter_callback($chat_id, $filter_type, $session_id) {
    $filters = [];
    
    switch ($filter_type) {
        case 'hd':
            $filters = ['quality' => '1080p'];
            answerCallbackQuery($callback_id, "🎬 Showing HD movies only");
            break;
        case 'theater':
            $filters = ['channel_type' => 'theater'];
            answerCallbackQuery($callback_id, "🎭 Showing theater prints only");
            break;
        case 'hindi':
            $filters = ['language' => 'Hindi'];
            answerCallbackQuery($callback_id, "🗣️ Showing Hindi movies only");
            break;
        case 'english':
            $filters = ['language' => 'English'];
            answerCallbackQuery($callback_id, "🔤 Showing English movies only");
            break;
        case 'clear':
            $filters = [];
            answerCallbackQuery($callback_id, "🧹 Filters cleared");
            break;
    }
    
    totalupload_controller($chat_id, 1, $filters, $session_id);
}
?>