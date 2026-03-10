<?php
// ==============================
// TELEGRAM API FUNCTIONS - ADD DEBUGGING
// ==============================

require_once 'config.php';
require_once 'database.php';

function apiRequest($method, $params = [], $is_multipart = false) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/" . $method;
    
    // Log API requests for debugging
    $log_file = __DIR__ . '/api_debug.log';
    file_put_contents($log_file, "[" . date('Y-m-d H:i:s') . "] API Request: $method\n", FILE_APPEND);
    file_put_contents($log_file, "URL: $url\n", FILE_APPEND);
    file_put_contents($log_file, "Params: " . json_encode($params) . "\n\n", FILE_APPEND);
    
    if ($is_multipart) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $res = curl_exec($ch);
        
        if ($res === false) {
            $error = curl_error($ch);
            file_put_contents($log_file, "CURL Error: $error\n", FILE_APPEND);
        }
        
        curl_close($ch);
        file_put_contents($log_file, "Response: $res\n\n", FILE_APPEND);
        return $res;
    } else {
        $options = [
            'http' => [
                'method' => 'POST',
                'content' => http_build_query($params),
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n"
            ]
        ];
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            file_put_contents($log_file, "file_get_contents failed\n", FILE_APPEND);
        } else {
            file_put_contents($log_file, "Response: $result\n\n", FILE_APPEND);
        }
        
        return $result;
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
    return json_decode($result, true);
}

// ... rest of telegram.php functions ...
