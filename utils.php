<?php
// ==============================
// UTILITY FUNCTIONS
// ==============================

require_once 'config.php';

function format_time_eta($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    }
    $mins = floor($seconds / 60);
    $secs = $seconds % 60;
    return $mins . ':' . str_pad($secs, 2, '0', STR_PAD_LEFT);
}

function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function detect_language($text) {
    $hindi_keywords = ['फिल्म', 'मूवी', 'हिंदी', 'चाहिए'];
    $hindi_chars = preg_match('/[\x{0900}-\x{097F}]/u', $text);
    
    if ($hindi_chars) {
        return 'hindi';
    }
    
    foreach ($hindi_keywords as $keyword) {
        if (strpos($text, $keyword) !== false) {
            return 'hindi';
        }
    }
    
    return 'english';
}

function is_valid_movie_query($text) {
    $text = strtolower(trim($text));
    
    // Commands are allowed
    if (strpos($text, '/') === 0) {
        return true;
    }
    
    // Too short
    if (strlen($text) < 2) {
        return false;
    }
    
    // Common non-movie phrases
    $invalid = ['hi', 'hello', 'hey', 'thanks', 'thank you', 'good morning', 'good night'];
    if (in_array($text, $invalid)) {
        return false;
    }
    
    // Must have at least one letter
    if (!preg_match('/[a-zA-Z]/', $text)) {
        return false;
    }
    
    return true;
}

function sanitize_filename($filename) {
    return preg_replace('/[^a-zA-Z0-9\-\_\.]/', '', $filename);
}

function get_client_ip() {
    $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key])) {
            $ip = trim(explode(',', $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function generate_random_string($length = 8) {
    return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
}

function array_group_by($array, $key) {
    $result = [];
    foreach ($array as $item) {
        $group_key = $item[$key] ?? 'unknown';
        if (!isset($result[$group_key])) {
            $result[$group_key] = [];
        }
        $result[$group_key][] = $item;
    }
    return $result;
}

function percentage($part, $total) {
    if ($total == 0) return 0;
    return round(($part / $total) * 100, 2);
}

function safe_json_decode($json, $default = []) {
    $result = json_decode($json, true);
    return is_array($result) ? $result : $default;
}

function log_error($message, $context = []) {
    $log = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $message;
    if (!empty($context)) {
        $log .= ' | ' . json_encode($context);
    }
    $log .= PHP_EOL;
    file_put_contents(LOG_FILE, $log, FILE_APPEND);
}

function check_rate_limit($user_id, $action, $limit = 10, $period = 60) {
    $file = __DIR__ . '/data/ratelimit_' . $user_id . '_' . $action . '.txt';
    $now = time();
    
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        $count = $data['count'] ?? 0;
        $reset = $data['reset'] ?? $now;
        
        if ($now > $reset) {
            // Reset period
            $count = 1;
            $reset = $now + $period;
        } else {
            $count++;
        }
    } else {
        $count = 1;
        $reset = $now + $period;
    }
    
    file_put_contents($file, json_encode(['count' => $count, 'reset' => $reset]));
    
    return $count <= $limit;
}
?>