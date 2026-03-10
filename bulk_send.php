<?php
// ==============================
// BULK SEND WITH PROGRESS TRACKING
// ==============================

require_once 'config.php';
require_once 'database.php';
require_once 'telegram.php';
require_once 'typing_eta.php';

function bulk_send_page($chat_id, $page, $session_id) {
    $all = get_all_movies_list();
    $pg = paginate_movies($all, $page, []);
    
    if (empty($pg['slice'])) {
        sendMessage($chat_id, "❌ No movies found on this page!");
        return;
    }
    
    $total = count($pg['slice']);
    $title = "Sending Page {$page} Movies";
    
    // Start batch send with ETA
    batchSendWithETA($chat_id, $pg['slice'], $title);
}

function bulk_send_all_pages($chat_id) {
    $all = get_all_movies_list();
    
    if (empty($all)) {
        sendMessage($chat_id, "📭 No movies to send!");
        return;
    }
    
    $total = count($all);
    $total_pages = ceil($total / ITEMS_PER_PAGE);
    
    // Confirmation keyboard
    $keyboard = [
        'inline_keyboard' => [
            [
                ['text' => '✅ Yes, Send All', 'callback_data' => 'confirm_bulk_all'],
                ['text' => '❌ Cancel', 'callback_data' => 'cancel_bulk']
            ]
        ]
    ];
    
    $message = "📦 <b>Bulk Send Confirmation</b>\n\n";
    $message .= "You are about to send <b>{$total}</b> movies from <b>{$total_pages}</b> pages.\n\n";
    $message .= "⚠️ This may take several minutes. Continue?";
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function bulk_send_filtered($chat_id, $filters) {
    $all = get_all_movies_list();
    $filtered = apply_filters($all, $filters);
    
    if (empty($filtered)) {
        sendMessage($chat_id, "❌ No movies match your filters!");
        return;
    }
    
    $title = "Sending Filtered Movies";
    batchSendWithETA($chat_id, $filtered, $title);
}

function bulk_send_quality($chat_id, $quality) {
    $all = get_all_movies_list();
    $filtered = [];
    
    foreach ($all as $movie) {
        if (stripos($movie['quality'] ?? '', $quality) !== false) {
            $filtered[] = $movie;
        }
    }
    
    if (empty($filtered)) {
        sendMessage($chat_id, "❌ No {$quality} movies found!");
        return;
    }
    
    $title = "Sending {$quality} Movies";
    batchSendWithETA($chat_id, $filtered, $title);
}

function bulk_send_language($chat_id, $language) {
    $all = get_all_movies_list();
    $filtered = [];
    
    foreach ($all as $movie) {
        if (stripos($movie['language'] ?? '', $language) !== false) {
            $filtered[] = $movie;
        }
    }
    
    if (empty($filtered)) {
        sendMessage($chat_id, "❌ No {$language} movies found!");
        return;
    }
    
    $title = "Sending {$language} Movies";
    batchSendWithETA($chat_id, $filtered, $title);
}

function bulk_send_latest($chat_id, $count = 20) {
    $all = get_all_movies_list();
    $latest = array_slice(array_reverse($all), 0, $count);
    
    if (empty($latest)) {
        sendMessage($chat_id, "📭 No latest movies found!");
        return;
    }
    
    $title = "Sending Latest {$count} Movies";
    batchSendWithETA($chat_id, $latest, $title);
}

// ==============================
// "SEND ALL" BUTTON INTEGRATION
// ==============================

function add_send_all_button($keyboard, $page, $session_id) {
    $keyboard['inline_keyboard'][] = [
        ['text' => '📦 SEND ALL (This Page)', 'callback_data' => 'bulk_send_page_' . $page . '_' . $session_id],
        ['text' => '📦 SEND ALL PAGES', 'callback_data' => 'bulk_send_all_' . $session_id]
    ];
    return $keyboard;
}

function handle_bulk_callback($chat_id, $data, $callback_id) {
    if (strpos($data, 'bulk_send_page_') === 0) {
        $parts = explode('_', $data);
        $page = $parts[3];
        $session_id = $parts[4] ?? '';
        bulk_send_page($chat_id, $page, $session_id);
        answerCallbackQuery($callback_id, "📦 Sending page {$page} movies...");
    }
    elseif (strpos($data, 'bulk_send_all_') === 0) {
        bulk_send_all_pages($chat_id);
        answerCallbackQuery($callback_id, "📦 Sending all movies...");
    }
    elseif ($data == 'confirm_bulk_all') {
        $all = get_all_movies_list();
        $title = "Sending All Movies";
        batchSendWithETA($chat_id, $all, $title);
        answerCallbackQuery($callback_id, "📦 Started sending all movies");
    }
    elseif ($data == 'cancel_bulk') {
        sendMessage($chat_id, "✅ Bulk send cancelled.");
        answerCallbackQuery($callback_id, "Cancelled");
    }
}
?>