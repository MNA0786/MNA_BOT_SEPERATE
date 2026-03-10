<?php
// ==============================
// TELEGRAM API FUNCTIONS - FIXED
// ==============================

// ===== NO OUTPUT BEFORE HEADERS! =====
// Ensure there's NO echo, print, or whitespace before functions

require_once 'config.php';
require_once 'database.php';

function apiRequest($method, $params = [], $is_multipart = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    if ($is_multipart) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    } else {
        $options = [
            'http' => [
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            ]
        ];
        return @file_get_contents($url, false, stream_context_create($options));
    }
}

function sendMessage($chat_id, $text, $reply_markup = null, $parse_mode = 'HTML', $protect = false) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true,
        'protect_content' => $protect
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    
    $result = apiRequest('sendMessage', $data);
    bot_log("Message sent to $chat_id");
    return json_decode($result, true);
}

function editMessage($chat_id, $message_id, $new_text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $new_text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) $data['reply_markup'] = json_encode($reply_markup);
    return apiRequest('editMessageText', $data);
}

function deleteMessage($chat_id, $message_id) {
    return apiRequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $message_id]);
}

function answerCallbackQuery($callback_query_id, $text = null, $show_alert = false) {
    $data = ['callback_query_id' => $callback_query_id, 'show_alert' => $show_alert];
    if ($text) $data['text'] = $text;
    return apiRequest('answerCallbackQuery', $data);
}

// ==============================
// CORE FUNCTIONS WITH PROTECTED CONTENT
// ==============================

function forwardMessage($chat_id, $from_chat_id, $message_id) {
    return apiRequest('forwardMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id
    ]);
}

function copyMessage($chat_id, $from_chat_id, $message_id, $protect = true) {
    return apiRequest('copyMessage', [
        'chat_id' => $chat_id,
        'from_chat_id' => $from_chat_id,
        'message_id' => $message_id,
        'protect_content' => $protect
    ]);
}

// ==============================
// DELIVERY FUNCTION WITH HEADER LOGIC
// ==============================

function deliver_item_to_chat($chat_id, $item, $requested_by = null) {
    global $delete_timers;
    
    if (!isset($item['channel_id']) || empty($item['channel_id'])) {
        $source_channel = MAIN_CHANNEL_ID;
    } else {
        $source_channel = $item['channel_id'];
    }
    
    $channel_type = $item['channel_type'] ?? 'main';
    $is_private = in_array($channel_type, ['private1', 'private2']);
    $protect_content = true; // Always protect
    
    // Attribution message
    if ($requested_by) {
        sendMessage($chat_id, "👤 <b>Requested by:</b> @$requested_by\n⏱️ <b>Auto-delete in:</b> 5 minutes", null, 'HTML');
    }
    
    // Send based on channel type
    if (!empty($item['message_id']) && is_numeric($item['message_id'])) {
        if ($is_private) {
            // Private channel → Copy (No header)
            $result = json_decode(copyMessage($chat_id, $source_channel, $item['message_id'], $protect_content), true);
        } else {
            // Public channel → Forward (With header for promotion)
            $result = json_decode(forwardMessage($chat_id, $source_channel, $item['message_id']), true);
        }
        
        if ($result && $result['ok']) {
            update_user_activity_by_id($chat_id, 'download');
            bot_log("Movie sent: {$item['movie_name']} from $channel_type");
            
            // Schedule auto-delete
            if (isset($result['result']['message_id'])) {
                schedule_deletion($chat_id, $result['result']['message_id'], AUTO_DELETE_TIME);
            }
            return true;
        }
    }
    
    // Fallback to text info
    send_text_fallback($chat_id, $item);
    return false;
}

function send_text_fallback($chat_id, $item) {
    $text = "🎬 <b>" . htmlspecialchars($item['movie_name'] ?? 'Unknown') . "</b>\n";
    $text .= "📊 Quality: " . htmlspecialchars($item['quality'] ?? 'Unknown') . "\n";
    $text .= "💾 Size: " . htmlspecialchars($item['size'] ?? 'Unknown') . "\n";
    $text .= "🗣️ Language: " . htmlspecialchars($item['language'] ?? 'Hindi') . "\n";
    $text .= "🎭 Channel: " . get_channel_display_name($item['channel_type'] ?? 'main', $chat_id) . "\n";
    
    if (!empty($item['channel_id']) && !empty($item['message_id'])) {
        $text .= "\n🔗 Direct Link: " . get_direct_channel_link($item['message_id'], $item['channel_id']);
    }
    
    sendMessage($chat_id, $text, null, 'HTML', true);
}

function get_direct_channel_link($message_id, $channel_id) {
    $channel_id_clean = str_replace('-100', '', $channel_id);
    return "https://t.me/c/" . $channel_id_clean . "/" . $message_id;
}
?>
