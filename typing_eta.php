<?php
// ==============================
// TYPING INDICATORS WITH ETA & PROGRESS BAR
// ==============================

require_once 'config.php';
require_once 'telegram.php';
require_once 'utils.php'; // Make sure utils.php is loaded first

// Store active progress messages
$active_progress = [];

function start_progress_tracking($chat_id, $initial_text = "⏳ Processing...") {
    global $active_progress;
    
    // Send typing action
    apiRequest('sendChatAction', ['chat_id' => $chat_id, 'action' => 'typing']);
    
    // Send initial progress message
    $result = sendMessage($chat_id, $initial_text . "\n\nETA: Calculating...");
    
    if ($result && isset($result['result']['message_id'])) {
        $progress_id = $result['result']['message_id'];
        $active_progress[$chat_id] = [
            'message_id' => $progress_id,
            'start_time' => time(),
            'items_total' => 0,
            'items_done' => 0
        ];
        return $progress_id;
    }
    
    return null;
}

function update_progress($chat_id, $progress_id, $current, $total, $status_text = "Processing") {
    global $active_progress;
    
    if (!isset($active_progress[$chat_id]) || $active_progress[$chat_id]['message_id'] != $progress_id) {
        return;
    }
    
    $start_time = $active_progress[$chat_id]['start_time'];
    $elapsed = time() - $start_time;
    
    // Calculate ETA
    if ($current > 0) {
        $avg_time_per_item = $elapsed / $current;
        $remaining_items = $total - $current;
        $eta_seconds = round($avg_time_per_item * $remaining_items);
    } else {
        $eta_seconds = 0;
    }
    
    // Progress bar
    $percent = $total > 0 ? round(($current / $total) * 100) : 0;
    $progress_bar = get_progress_bar($percent, 15);
    
    // Format times - USE FUNCTION FROM UTILS.PHP
    $eta_formatted = format_time_eta($eta_seconds);
    $elapsed_formatted = format_time_eta($elapsed);
    
    // Build message
    $text = "⏳ <b>$status_text</b>\n\n";
    $text .= "$progress_bar <b>$percent%</b>\n";
    $text .= "✅ Done: <b>$current/$total</b>\n";
    $text .= "⏱️ Elapsed: <b>$elapsed_formatted</b>\n";
    $text .= "⏳ Remaining: <b>$eta_formatted</b>\n";
    
    if ($current < $total) {
        $text .= "\n<i>Please wait...</i>";
    }
    
    editMessage($chat_id, $progress_id, $text);
    
    // Update tracking
    $active_progress[$chat_id]['items_done'] = $current;
    $active_progress[$chat_id]['items_total'] = $total;
}

function stop_progress_tracking($chat_id, $progress_id, $final_text = "✅ Complete!") {
    global $active_progress;
    
    if (isset($active_progress[$chat_id]) && $active_progress[$chat_id]['message_id'] == $progress_id) {
        $data = $active_progress[$chat_id];
        $elapsed = time() - $data['start_time'];
        $elapsed_formatted = format_time_eta($elapsed);
        
        $final_message = $final_text . "\n\n⏱️ Time taken: <b>$elapsed_formatted</b>";
        editMessage($chat_id, $progress_id, $final_message);
        
        unset($active_progress[$chat_id]);
    }
}

function get_progress_bar($percent, $length = 10) {
    $filled = round(($percent / 100) * $length);
    $empty = $length - $filled;
    return "[" . str_repeat("█", $filled) . str_repeat("░", $empty) . "]";
}

// ===== REMOVED: function format_time_eta($seconds) { ... } =====
// Yeh function utils.php mein already hai, isliye yahan se hataya

// ==============================
// ENHANCED SEND FUNCTIONS WITH ETA
// ==============================

function sendMessageWithETA($chat_id, $text, $items_count = 1) {
    // Send typing action
    apiRequest('sendChatAction', ['chat_id' => $chat_id, 'action' => 'typing']);
    
    // Calculate ETA (assume 0.5 sec per item)
    $eta_seconds = $items_count * 0.5;
    
    // Send initial message
    $eta_text = "⏳ " . ($items_count > 1 ? "Sending $items_count items..." : "Sending...");
    $eta_text .= "\n⏱️ ETA: " . format_time_eta($eta_seconds);
    
    $result = sendMessage($chat_id, $eta_text);
    
    if ($items_count == 1) {
        usleep($eta_seconds * 1000000); // Convert to microseconds
        editMessage($chat_id, $result['result']['message_id'], $text);
    }
    
    return $result;
}

function batchSendWithETA($chat_id, $items, $title = "Sending items") {
    $total = count($items);
    if ($total == 0) return;
    
    // Start progress tracking
    $progress_id = start_progress_tracking($chat_id, "📦 <b>$title</b>\n\nPreparing...");
    
    $success = 0;
    $failed = 0;
    
    for ($i = 0; $i < $total; $i++) {
        // Update progress
        update_progress($chat_id, $progress_id, $i, $total, $title);
        
        // Send typing action
        apiRequest('sendChatAction', ['chat_id' => $chat_id, 'action' => 'typing']);
        
        // Deliver item
        try {
            $result = deliver_item_to_chat($chat_id, $items[$i]);
            if ($result) $success++; else $failed++;
        } catch (Exception $e) {
            $failed++;
        }
        
        // Small delay
        usleep(300000); // 0.3 sec
    }
    
    // Final update
    $final_text = "✅ <b>Complete!</b>\n\n";
    $final_text .= "📦 Total: $total\n";
    $final_text .= "✅ Success: $success\n";
    $final_text .= "❌ Failed: $failed\n";
    $final_text .= "📊 Rate: " . round(($success / $total) * 100, 2) . "%";
    
    stop_progress_tracking($chat_id, $progress_id, $final_text);
}

// ==============================
// TYPING INDICATOR FOR SEARCH
// ==============================

function searchWithTyping($chat_id, $query, $callback) {
    // Start typing
    apiRequest('sendChatAction', ['chat_id' => $chat_id, 'action' => 'typing']);
    
    // Small delay to simulate thinking
    usleep(500000); // 0.5 sec
    
    // Execute search
    $results = $callback($query);
    
    // Stop typing
    apiRequest('sendChatAction', ['chat_id' => $chat_id, 'action' => 'cancel']);
    
    return $results;
}
?>
