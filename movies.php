<?php
// ==============================
// MOVIE SEARCH & DELIVERY FUNCTIONS
// ==============================

require_once 'config.php';
require_once 'database.php';
require_once 'channels.php';
require_once 'telegram.php';
require_once 'typing_eta.php';
require_once 'pagination.php';

// ==============================
// SMART SEARCH ENGINE
// ==============================

function smart_search($query) {
    global $movie_messages;
    $query_lower = strtolower(trim($query));
    $results = [];
    
    $is_theater_search = false;
    $theater_keywords = ['theater', 'theatre', 'print', 'hdcam', 'camrip', 'hdrip'];
    foreach ($theater_keywords as $keyword) {
        if (strpos($query_lower, $keyword) !== false) {
            $is_theater_search = true;
            $query_lower = str_replace($keyword, '', $query_lower);
            break;
        }
    }
    $query_lower = trim($query_lower);
    
    foreach ($movie_messages as $movie => $entries) {
        $score = 0;
        
        // Channel type matching
        foreach ($entries as $entry) {
            $entry_channel_type = $entry['channel_type'] ?? 'main';
            
            if ($is_theater_search && $entry_channel_type == 'theater') {
                $score += 20;
            } elseif (!$is_theater_search && $entry_channel_type == 'main') {
                $score += 10;
            }
            
            if (in_array($entry_channel_type, ['backup', 'serial', 'private1', 'private2'])) {
                $score += 5;
            }
        }
        
        // Match score
        if ($movie == $query_lower) {
            $score = 100;
        } elseif (strpos($movie, $query_lower) !== false) {
            $score = 80 - (strlen($movie) - strlen($query_lower));
        } else {
            similar_text($movie, $query_lower, $similarity);
            if ($similarity > 60) $score = $similarity;
        }
        
        // Quality bonus
        foreach ($entries as $entry) {
            if (stripos($entry['quality'] ?? '', '1080') !== false) $score += 5;
            if (stripos($entry['quality'] ?? '', '720') !== false) $score += 3;
            if (stripos($entry['language'] ?? '', 'hindi') !== false) $score += 2;
        }
        
        if ($score > 0) {
            $channel_types = array_column($entries, 'channel_type');
            $results[$movie] = [
                'score' => $score,
                'count' => count($entries),
                'latest_entry' => end($entries),
                'qualities' => array_unique(array_column($entries, 'quality')),
                'has_theater' => in_array('theater', $channel_types),
                'has_main' => in_array('main', $channel_types),
                'has_serial' => in_array('serial', $channel_types),
                'has_backup' => in_array('backup', $channel_types),
                'all_channels' => array_unique($channel_types)
            ];
        }
    }
    
    uasort($results, function($a, $b) { return $b['score'] - $a['score']; });
    return array_slice($results, 0, MAX_SEARCH_RESULTS);
}

// ==============================
// ADVANCED SEARCH
// ==============================

function advanced_search($chat_id, $query, $user_id = null) {
    global $movie_messages;
    
    if (strlen($query) < 2) {
        sendMessage($chat_id, "❌ Please enter at least 2 characters");
        return;
    }
    
    // Typing indicator with ETA
    $progress_id = start_progress_tracking($chat_id, "🔍 Searching for '$query'");
    
    $found = smart_search($query);
    
    if (!empty($found)) {
        update_stats_field('successful_searches', 1);
        
        // Stop typing
        stop_progress_tracking($chat_id, $progress_id, "✅ Found " . count($found) . " movies!");
        
        $msg = "🔍 Found " . count($found) . " movies for '$query':\n\n";
        $i = 1;
        foreach ($found as $movie => $data) {
            $quality_info = !empty($data['qualities']) ? implode('/', $data['qualities']) : 'Unknown';
            $channel_icons = get_channel_icons($data);
            $msg .= "$i. $movie $channel_icons (" . $data['count'] . " versions)\n";
            $i++;
            if ($i > 10) break;
        }
        
        sendMessage($chat_id, $msg);
        
        // Inline keyboard
        $keyboard = build_movie_selection_keyboard(array_slice(array_keys($found), 0, 5));
        sendMessage($chat_id, "🚀 Top matches (click for info):", $keyboard);
        
        update_user_activity($user_id, 'found_movie');
        update_user_activity($user_id, 'search');
        
    } else {
        update_stats_field('failed_searches', 1);
        stop_progress_tracking($chat_id, $progress_id, "❌ Movie not found!");
        
        $not_found_msg = "😔 This movie isn't available yet!\n\n📝 You can request it here: " . REQUEST_GROUP_USERNAME;
        sendMessage($chat_id, $not_found_msg);
        
        // Auto-request option
        $keyboard = [
            'inline_keyboard' => [[
                ['text' => '📝 Request This Movie', 'callback_data' => 'auto_request_' . base64_encode($query)]
            ]]
        ];
        sendMessage($chat_id, "💡 Click below to request:", $keyboard);
        
        // Add to waiting list
        add_to_waiting_list($query, $chat_id, $user_id);
    }
    
    update_stats_field('total_searches', 1);
    update_user_activity($user_id, 'search');
}

function get_channel_icons($data) {
    $icons = '';
    if ($data['has_theater']) $icons .= '🎭';
    if ($data['has_main']) $icons .= '🍿';
    if ($data['has_serial']) $icons .= '📺';
    if ($data['has_backup']) $icons .= '🔒';
    return $icons ? "($icons)" : '';
}

function build_movie_selection_keyboard($movies) {
    $keyboard = ['inline_keyboard' => []];
    foreach ($movies as $movie) {
        $keyboard['inline_keyboard'][] = [[
            'text' => "🎬 " . ucwords($movie),
            'callback_data' => 'movie_' . base64_encode($movie)
        ]];
    }
    $keyboard['inline_keyboard'][] = [[
        'text' => "📝 Request Different Movie",
        'callback_data' => 'request_movie'
    ]];
    return $keyboard;
}

function add_to_waiting_list($movie, $chat_id, $user_id) {
    global $waiting_users;
    $movie_lower = strtolower($movie);
    if (!isset($waiting_users[$movie_lower])) $waiting_users[$movie_lower] = [];
    $waiting_users[$movie_lower][] = [$chat_id, $user_id];
}

function notify_waiting_users($movie_name, $channel_type) {
    global $waiting_users;
    $movie_lower = strtolower($movie_name);
    
    if (!empty($waiting_users[$movie_lower])) {
        $channel_link = get_channel_username_link($channel_type);
        $notification = "🎉 <b>Good News!</b>\n\nYour requested movie <b>$movie_name</b> has been added!\n\nJoin: $channel_link";
        
        foreach ($waiting_users[$movie_lower] as $user_data) {
            sendMessage($user_data[0], $notification, null, 'HTML');
        }
        unset($waiting_users[$movie_lower]);
    }
}

// ==============================
// LATEST MOVIES
// ==============================

function show_latest_movies($chat_id, $limit = 10) {
    $all_movies = get_all_movies_list();
    $latest = array_reverse(array_slice($all_movies, -$limit));
    
    if (empty($latest)) {
        sendMessage($chat_id, "📭 No movies found!");
        return;
    }
    
    $msg = "🎬 <b>Latest $limit Movies</b>\n\n";
    foreach ($latest as $index => $movie) {
        $channel_icon = get_channel_display_name($movie['channel_type'] ?? 'main', $chat_id);
        $msg .= ($index + 1) . ". $channel_icon <b>{$movie['movie_name']}</b>\n";
        $msg .= "   📊 {$movie['quality']} | 🗣️ {$movie['language']}\n\n";
    }
    
    $keyboard = [
        'inline_keyboard' => [[
            ['text' => '📥 Get All Info', 'callback_data' => 'bulk_latest']
        ]]
    ];
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}
?>