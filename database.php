<?php
// ==============================
// DATABASE FUNCTIONS (CSV + JSON)
// ==============================

require_once 'config.php';
require_once 'utils.php';

function initialize_files() {
    $files = [
        CSV_FILE => "movie_name,message_id,date,video_path,quality,size,language,channel_type,channel_id,channel_username\n",
        USERS_FILE => json_encode(['users' => [], 'total_requests' => 0, 'message_logs' => []], JSON_PRETTY_PRINT),
        STATS_FILE => json_encode([
            'total_movies' => 0, 'total_users' => 0, 'total_searches' => 0,
            'total_downloads' => 0, 'successful_searches' => 0, 'failed_searches' => 0,
            'daily_activity' => [], 'last_updated' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT),
        REQUEST_FILE => json_encode(['requests' => [], 'pending_approval' => [], 'completed_requests' => [], 'user_request_count' => []], JSON_PRETTY_PRINT),
        SETTINGS_FILE => json_encode(['user_settings' => []], JSON_PRETTY_PRINT)
    ];
    
    foreach ($files as $file => $content) {
        $dir = dirname($file);
        if (!file_exists($dir)) mkdir($dir, 0777, true);
        if (!file_exists($file)) file_put_contents($file, $content);
    }
    
    if (!file_exists(BACKUP_DIR)) mkdir(BACKUP_DIR, 0777, true);
    if (!file_exists(LOG_FILE)) file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] SYSTEM: Files initialized\n");
}

// ==============================
// CSV FUNCTIONS
// ==============================
function load_and_clean_csv($filename = CSV_FILE) {
    global $movie_messages, $movie_cache;
    
    if (!file_exists($filename)) return [];
    
    $data = [];
    $handle = fopen($filename, "r");
    if ($handle) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 3 && !empty(trim($row[0]))) {
                $entry = [
                    'movie_name' => trim($row[0]),
                    'message_id_raw' => trim($row[1] ?? ''),
                    'date' => trim($row[2] ?? ''),
                    'video_path' => trim($row[3] ?? ''),
                    'quality' => trim($row[4] ?? 'Unknown'),
                    'size' => trim($row[5] ?? 'Unknown'),
                    'language' => trim($row[6] ?? 'Hindi'),
                    'channel_type' => trim($row[7] ?? get_channel_type_by_id($row[8] ?? '')),
                    'channel_id' => trim($row[8] ?? ''),
                    'channel_username' => trim($row[9] ?? '')
                ];
                
                if (is_numeric($entry['message_id_raw'])) {
                    $entry['message_id'] = intval($entry['message_id_raw']);
                }
                
                $data[] = $entry;
                
                $movie_key = strtolower($entry['movie_name']);
                if (!isset($movie_messages[$movie_key])) $movie_messages[$movie_key] = [];
                $movie_messages[$movie_key][] = $entry;
            }
        }
        fclose($handle);
    }
    
    $movie_cache = ['data' => $data, 'timestamp' => time()];
    update_total_movies(count($data));
    return $data;
}

function append_movie($movie_name, $message_id_raw, $date = null, $video_path = '', $quality = 'Unknown', $size = 'Unknown', $language = 'Hindi', $channel_type = 'main') {
    global $movie_messages, $movie_cache, $waiting_users;
    
    if (empty(trim($movie_name))) return false;
    if ($date === null) $date = date('d-m-Y');
    
    $channel_info = get_channel_info_by_type($channel_type);
    
    $entry = [
        $movie_name, $message_id_raw, $date, $video_path, $quality, $size, $language,
        $channel_type, $channel_info['id'], $channel_info['username']
    ];
    
    $handle = fopen(CSV_FILE, "a");
    if ($handle) {
        fputcsv($handle, $entry);
        fclose($handle);
        
        $item = [
            'movie_name' => $movie_name,
            'message_id_raw' => $message_id_raw,
            'date' => $date,
            'video_path' => $video_path,
            'quality' => $quality,
            'size' => $size,
            'language' => $language,
            'channel_type' => $channel_type,
            'channel_id' => $channel_info['id'],
            'channel_username' => $channel_info['username'],
            'message_id' => is_numeric($message_id_raw) ? intval($message_id_raw) : null
        ];
        
        $movie_key = strtolower($movie_name);
        if (!isset($movie_messages[$movie_key])) $movie_messages[$movie_key] = [];
        $movie_messages[$movie_key][] = $item;
        $movie_cache = [];
        
        notify_waiting_users($movie_name, $channel_type);
        update_total_movies(1);
        bot_log("Movie added: $movie_name from $channel_type");
        return true;
    }
    return false;
}

function get_channel_info_by_type($channel_type) {
    switch ($channel_type) {
        case 'main': return ['id' => MAIN_CHANNEL_ID, 'username' => MAIN_CHANNEL];
        case 'serial': return ['id' => SERIAL_CHANNEL_ID, 'username' => SERIAL_CHANNEL];
        case 'theater': return ['id' => THEATER_CHANNEL_ID, 'username' => THEATER_CHANNEL];
        case 'backup': return ['id' => BACKUP_CHANNEL_ID, 'username' => BACKUP_CHANNEL_USERNAME];
        case 'private1': return ['id' => PRIVATE_CHANNEL_1_ID, 'username' => ''];
        case 'private2': return ['id' => PRIVATE_CHANNEL_2_ID, 'username' => ''];
        default: return ['id' => MAIN_CHANNEL_ID, 'username' => MAIN_CHANNEL];
    }
}

function get_all_movies_list() {
    global $movie_cache;
    if (!empty($movie_cache) && (time() - $movie_cache['timestamp']) < CACHE_EXPIRY) {
        return $movie_cache['data'];
    }
    return load_and_clean_csv();
}

function update_total_movies($increment = 1) {
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats['total_movies'] = ($stats['total_movies'] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}

function get_stats() {
    return file_exists(STATS_FILE) ? json_decode(file_get_contents(STATS_FILE), true) : [];
}

function bot_log($message, $type = 'INFO') {
    $log_entry = "[" . date('Y-m-d H:i:s') . "] $type: $message\n";
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

initialize_files();
?>