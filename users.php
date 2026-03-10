<?php
// ==============================
// USER MANAGEMENT FUNCTIONS
// ==============================

require_once 'config.php';
require_once 'database.php';

function update_user_data($user_id, $user_info = []) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (!isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id] = [
            'first_name' => $user_info['first_name'] ?? '',
            'last_name' => $user_info['last_name'] ?? '',
            'username' => $user_info['username'] ?? '',
            'joined' => date('Y-m-d H:i:s'),
            'last_active' => date('Y-m-d H:i:s'),
            'points' => 0,
            'total_searches' => 0,
            'total_downloads' => 0,
            'request_count' => 0,
            'last_request_date' => null
        ];
        
        $stats = json_decode(file_get_contents(STATS_FILE), true);
        $stats['total_users'] = ($stats['total_users'] ?? 0) + 1;
        file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
        
        bot_log("New user registered: $user_id");
    }
    
    $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
    file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    
    return $users_data['users'][$user_id];
}

function update_user_activity($user_id, $action) {
    if (!$user_id) return;
    
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    
    if (isset($users_data['users'][$user_id])) {
        $points_map = [
            'search' => 1,
            'found_movie' => 5,
            'daily_login' => 10,
            'movie_request' => 2,
            'download' => 3
        ];
        
        $users_data['users'][$user_id]['points'] += ($points_map[$action] ?? 0);
        if ($action == 'search') $users_data['users'][$user_id]['total_searches']++;
        if ($action == 'download') $users_data['users'][$user_id]['total_downloads']++;
        
        $users_data['users'][$user_id]['last_active'] = date('Y-m-d H:i:s');
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    }
}

function update_user_activity_by_id($chat_id, $action) {
    if (is_numeric($chat_id) && $chat_id > 0) {
        update_user_activity($chat_id, $action);
    }
}

function get_username_by_id($user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    return $users_data['users'][$user_id]['username'] ?? "User$user_id";
}

// ==============================
// USER STATS & LEADERBOARD
// ==============================

function show_user_stats($chat_id, $user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $user = $users_data['users'][$user_id] ?? null;
    
    if (!$user) {
        sendMessage($chat_id, "❌ User data not found!");
        return;
    }
    
    $username = $user['username'] ? "@" . $user['username'] : "User";
    
    $msg = "👤 <b>Your Statistics</b>\n\n";
    $msg .= "🆔 ID: <code>$user_id</code>\n";
    $msg .= "📛 Name: $username\n";
    $msg .= "📅 Joined: " . ($user['joined'] ?? 'N/A') . "\n\n";
    
    $msg .= "📊 <b>Activity:</b>\n";
    $msg .= "• 🔍 Searches: " . ($user['total_searches'] ?? 0) . "\n";
    $msg .= "• 📥 Downloads: " . ($user['total_downloads'] ?? 0) . "\n";
    $msg .= "• 📝 Requests: " . ($user['request_count'] ?? 0) . "\n";
    $msg .= "• ⭐ Points: " . ($user['points'] ?? 0) . "\n\n";
    
    $msg .= "🏆 <b>Rank:</b> " . calculate_user_rank($user['points'] ?? 0);
    
    $keyboard = [
        'inline_keyboard' => [[
            ['text' => '📈 Leaderboard', 'callback_data' => 'show_leaderboard'],
            ['text' => '⚙️ Settings', 'callback_data' => 'open_settings']
        ]]
    ];
    
    sendMessage($chat_id, $msg, $keyboard, 'HTML');
}

function show_leaderboard($chat_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    $users = $users_data['users'] ?? [];
    
    if (empty($users)) {
        sendMessage($chat_id, "📭 No users yet!");
        return;
    }
    
    uasort($users, function($a, $b) {
        return ($b['points'] ?? 0) - ($a['points'] ?? 0);
    });
    
    $msg = "🏆 <b>Top Users Leaderboard</b>\n\n";
    $i = 1;
    
    foreach (array_slice($users, 0, 10) as $uid => $user) {
        $points = $user['points'] ?? 0;
        $name = $user['username'] ? "@" . $user['username'] : "User" . substr($uid, -4);
        $medal = $i == 1 ? "🥇" : ($i == 2 ? "🥈" : ($i == 3 ? "🥉" : "🔸"));
        
        $msg .= "$medal $i. $name\n";
        $msg .= "   ⭐ $points points | " . calculate_user_rank($points) . "\n\n";
        $i++;
    }
    
    sendMessage($chat_id, $msg, null, 'HTML');
}

function calculate_user_rank($points) {
    if ($points >= 1000) return "🎖️ Elite";
    if ($points >= 500) return "🔥 Pro";
    if ($points >= 250) return "⭐ Advanced";
    if ($points >= 100) return "🚀 Intermediate";
    if ($points >= 50) return "👍 Beginner";
    return "🌱 Newbie";
}

// ==============================
// REQUEST SYSTEM
// ==============================

function can_user_request($user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $today = date('Y-m-d');
    
    $today_count = 0;
    foreach ($requests_data['requests'] ?? [] as $req) {
        if ($req['user_id'] == $user_id && $req['date'] == $today) {
            $today_count++;
        }
    }
    
    return $today_count < DAILY_REQUEST_LIMIT;
}

function add_movie_request($user_id, $movie_name, $language = 'hindi') {
    if (!can_user_request($user_id)) return false;
    
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $username = get_username_by_id($user_id);
    
    $request_id = uniqid();
    $requests_data['requests'][] = [
        'id' => $request_id,
        'user_id' => $user_id,
        'username' => $username,
        'movie_name' => $movie_name,
        'language' => $language,
        'date' => date('Y-m-d'),
        'time' => date('H:i:s'),
        'status' => 'pending'
    ];
    
    $requests_data['user_request_count'][$user_id] = ($requests_data['user_request_count'][$user_id] ?? 0) + 1;
    file_put_contents(REQUEST_FILE, json_encode($requests_data, JSON_PRETTY_PRINT));
    
    // Notify admin
    $admin_msg = "🎯 New Request\nMovie: $movie_name\nUser: @$username\nID: $user_id";
    sendMessage(ADMIN_ID, $admin_msg);
    
    update_user_activity($user_id, 'movie_request');
    update_user_request_count($user_id);
    
    return true;
}

function update_user_request_count($user_id) {
    $users_data = json_decode(file_get_contents(USERS_FILE), true);
    if (isset($users_data['users'][$user_id])) {
        $users_data['users'][$user_id]['request_count'] = ($users_data['users'][$user_id]['request_count'] ?? 0) + 1;
        $users_data['users'][$user_id]['last_request_date'] = date('Y-m-d');
        file_put_contents(USERS_FILE, json_encode($users_data, JSON_PRETTY_PRINT));
    }
}

function show_user_requests($chat_id, $user_id) {
    $requests_data = json_decode(file_get_contents(REQUEST_FILE), true);
    $user_requests = [];
    
    foreach ($requests_data['requests'] ?? [] as $req) {
        if ($req['user_id'] == $user_id) $user_requests[] = $req;
    }
    
    if (empty($user_requests)) {
        sendMessage($chat_id, "📭 No requests yet!");
        return;
    }
    
    $msg = "📝 <b>Your Requests</b>\n\n";
    foreach (array_slice($user_requests, 0, 10) as $req) {
        $status = $req['status'] == 'completed' ? '✅' : '⏳';
        $msg .= "$status <b>{$req['movie_name']}</b> ({$req['date']})\n";
    }
    
    sendMessage($chat_id, $msg, null, 'HTML');
}

function update_stats_field($field, $increment = 1) {
    $stats = json_decode(file_get_contents(STATS_FILE), true);
    $stats[$field] = ($stats[$field] ?? 0) + $increment;
    $stats['last_updated'] = date('Y-m-d H:i:s');
    
    $today = date('Y-m-d');
    if (!isset($stats['daily_activity'][$today])) {
        $stats['daily_activity'][$today] = ['searches' => 0, 'downloads' => 0, 'users' => 0];
    }
    
    if ($field == 'total_searches') $stats['daily_activity'][$today]['searches'] += $increment;
    if ($field == 'total_downloads') $stats['daily_activity'][$today]['downloads'] += $increment;
    
    file_put_contents(STATS_FILE, json_encode($stats, JSON_PRETTY_PRINT));
}
?>