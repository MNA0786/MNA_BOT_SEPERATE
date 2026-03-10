<?php
// ==============================
// AUTO-DELETE TIMER WITH PROGRESS BAR
// ==============================

require_once 'config.php';
require_once 'telegram.php';
require_once 'utils.php'; // Make sure utils is loaded

$delete_timers = [];

function schedule_deletion($chat_id, $message_id, $seconds = 300) {
    global $delete_timers;
    
    $timer_id = uniqid('del_', true);
    $delete_time = time() + $seconds;
    
    $delete_timers[$timer_id] = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'delete_time' => $delete_time,
        'seconds' => $seconds,
        'active' => true
    ];
    
    // Send timer message
    send_timer_message($chat_id, $message_id, $seconds, $timer_id);
    
    return $timer_id;
}

function send_timer_message($chat_id, $target_message_id, $seconds, $timer_id) {
    $time_str = format_time_eta($seconds);
    
    $text = "⏱️ <b>Auto-delete Timer</b>\n\n";
    $text .= "This message will auto-delete in:\n";
    $text .= "<b>$time_str</b>\n\n";
    $text .= get_progress_bar_timer(0, 15) . " 0%\n\n";
    $text .= "👇 Click to cancel:";
    
    $keyboard = [
        'inline_keyboard' => [[
            ['text' => '❌ Cancel Auto-delete', 'callback_data' => 'cancel_delete_' . $timer_id]
        ]]
    ];
    
    sendMessage($chat_id, $text, $keyboard, 'HTML');
}

function update_timer_message($chat_id, $message_id, $timer_id) {
    global $delete_timers;
    
    if (!isset($delete_timers[$timer_id]) || !$delete_timers[$timer_id]['active']) {
        return;
    }
    
    $timer = $delete_timers[$timer_id];
    $remaining = $timer['delete_time'] - time();
    
    if ($remaining <= 0) {
        // Time's up - delete the target message
        deleteMessage($chat_id, $timer['message_id']);
        deleteMessage($chat_id, $message_id); // Delete timer message too
        unset($delete_timers[$timer_id]);
        return;
    }
    
    $time_str = format_time_eta($remaining);
    $percent = round((($timer['seconds'] - $remaining) / $timer['seconds']) * 100);
    $progress_bar = get_progress_bar_timer($percent, 15);
    
    $text = "⏱️ <b>Auto-delete Timer</b>\n\n";
    $text .= "This message will auto-delete in:\n";
    $text .= "<b>$time_str</b>\n\n";
    $text .= "$progress_bar <b>$percent%</b>\n\n";
    $text .= "👇 Click to cancel:";
    
    $keyboard = [
        'inline_keyboard' => [[
            ['text' => '❌ Cancel Auto-delete', 'callback_data' => 'cancel_delete_' . $timer_id]
        ]]
    ];
    
    editMessage($chat_id, $message_id, $text, $keyboard);
}

function get_progress_bar_timer($percent, $length = 10) {
    $filled = round(($percent / 100) * $length);
    $empty = $length - $filled;
    
    // Use different colors for timer
    $bar = "⏳";
    if ($percent < 25) $bar = "🟢";
    elseif ($percent < 50) $bar = "🟡";
    elseif ($percent < 75) $bar = "🟠";
    else $bar = "🔴";
    
    return $bar . " [" . str_repeat("█", $filled) . str_repeat("░", $empty) . "]";
}

function cancel_deletion($chat_id, $timer_id) {
    global $delete_timers;
    
    if (isset($delete_timers[$timer_id]) && $delete_timers[$timer_id]['active']) {
        $delete_timers[$timer_id]['active'] = false;
        
        sendMessage($chat_id, "✅ Auto-delete cancelled!\n\nThe message will not be deleted.");
        bot_log("Auto-delete cancelled: $timer_id");
        return true;
    }
    
    return false;
}

// Run this function every minute via cron or check in webhook
function process_delete_timers() {
    global $delete_timers;
    
    $current_time = time();
    
    foreach ($delete_timers as $timer_id => $timer) {
        if (!$timer['active']) {
            unset($delete_timers[$timer_id]);
            continue;
        }
        
        if ($current_time >= $timer['delete_time']) {
            // Delete the message
            deleteMessage($timer['chat_id'], $timer['message_id']);
            unset($delete_timers[$timer_id]);
            bot_log("Auto-deleted message {$timer['message_id']}");
        }
    }
}

// Add delete button to any message
function add_delete_button($chat_id, $message_id, $text, $seconds = 300) {
    $timer_id = schedule_deletion($chat_id, $message_id, $seconds);
    
    $keyboard = [
        'inline_keyboard' => [[
            ['text' => '⏱️ Delete in ' . format_time_eta($seconds), 'callback_data' => 'show_timer_' . $timer_id],
            ['text' => '❌ Delete Now', 'callback_data' => 'delete_now_' . $message_id]
        ]]
    ];
    
    return sendMessage($chat_id, $text, $keyboard, 'HTML');
}
?>
