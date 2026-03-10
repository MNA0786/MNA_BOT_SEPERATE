<?php
// ==============================
// CHANNEL MANAGEMENT FUNCTIONS
// ==============================

require_once 'config.php';
require_once 'telegram.php';

// ==============================
// CHANNEL TYPE DETECTION
// ==============================

function get_channel_type_by_id($channel_id) {
    $channel_id = strval($channel_id);
    
    if ($channel_id == MAIN_CHANNEL_ID) return 'main';
    if ($channel_id == SERIAL_CHANNEL_ID) return 'serial';
    if ($channel_id == THEATER_CHANNEL_ID) return 'theater';
    if ($channel_id == BACKUP_CHANNEL_ID) return 'backup';
    if ($channel_id == PRIVATE_CHANNEL_1_ID) return 'private1';
    if ($channel_id == PRIVATE_CHANNEL_2_ID) return 'private2';
    if ($channel_id == REQUEST_GROUP_ID) return 'request_group';
    
    return 'other';
}

function get_channel_id_by_username($username) {
    $username = strtolower(trim($username));
    
    $channel_map = [
        '@entertainmenttadka786' => MAIN_CHANNEL_ID,
        '@entertainment_tadka_serial_786' => SERIAL_CHANNEL_ID,
        '@threater_print_movies' => THEATER_CHANNEL_ID,
        '@etbackup' => BACKUP_CHANNEL_ID,
        '@entertainmenttadka7860' => REQUEST_GROUP_ID,
        'entertainmenttadka786' => MAIN_CHANNEL_ID,
        'entertainment_tadka_serial_786' => SERIAL_CHANNEL_ID,
        'threater_print_movies' => THEATER_CHANNEL_ID,
        'etbackup' => BACKUP_CHANNEL_ID,
        'entertainmenttadka7860' => REQUEST_GROUP_ID,
    ];
    
    return $channel_map[$username] ?? null;
}

// ==============================
// DISPLAY FUNCTIONS (Public/Private Logic)
// ==============================

function get_channel_display_name($channel_type, $user_id = null) {
    $is_admin = ($user_id == ADMIN_ID);
    
    // Private channels - hide from non-admin
    if (!$is_admin && in_array($channel_type, ['private1', 'private2'])) {
        return '🔒 Private Channel';
    }
    
    $names = [
        'main' => '🍿 Main Channel',
        'serial' => '📺 Serial Channel',
        'theater' => '🎭 Theater Prints',
        'backup' => '🔒 Backup Channel',
        'private1' => '🔐 Private Channel 1',
        'private2' => '🔐 Private Channel 2',
        'request_group' => '📢 Request Group',
        'other' => '📢 Other Channel'
    ];
    
    return $names[$channel_type] ?? '📢 Unknown Channel';
}

function get_channel_username_link($channel_type, $user_id = null) {
    $is_admin = ($user_id == ADMIN_ID);
    
    // Private channels - no link for non-admin
    if (!$is_admin && in_array($channel_type, ['private1', 'private2'])) {
        return '#private-channel';
    }
    
    switch ($channel_type) {
        case 'main': return "https://t.me/" . ltrim(MAIN_CHANNEL, '@');
        case 'serial': return "https://t.me/" . ltrim(SERIAL_CHANNEL, '@');
        case 'theater': return "https://t.me/" . ltrim(THEATER_CHANNEL, '@');
        case 'backup': return "https://t.me/" . ltrim(BACKUP_CHANNEL_USERNAME, '@');
        case 'request_group': return "https://t.me/" . ltrim(REQUEST_GROUP_USERNAME, '@');
        default: return "https://t.me/EntertainmentTadka786";
    }
}

// ==============================
// CHANNEL INFO DISPLAY
// ==============================

function show_channel_info($chat_id, $user_id) {
    $public_channels = json_decode(PUBLIC_CHANNELS, true);
    
    $message = "📢 <b>Join Our Public Channels</b>\n\n";
    
    foreach ($public_channels as $channel) {
        $message .= "{$channel['name']}: {$channel['username']}\n";
        $message .= "🔗 <a href=\"{$channel['link']}\">Click to join</a>\n\n";
    }
    
    $message .= "🔔 <b>Don't forget to join all channels!</b>";

    $keyboard = build_channel_keyboard($public_channels);
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function build_channel_keyboard($channels) {
    $keyboard = ['inline_keyboard' => []];
    $row = [];
    
    foreach ($channels as $index => $channel) {
        $row[] = ['text' => $channel['name'], 'url' => $channel['link']];
        if (($index + 1) % 2 == 0) {
            $keyboard['inline_keyboard'][] = $row;
            $row = [];
        }
    }
    
    if (!empty($row)) {
        $keyboard['inline_keyboard'][] = $row;
    }
    
    return $keyboard;
}

function show_main_channel_info($chat_id) {
    $stats = get_stats();
    
    $message = "🍿 <b>Main Channel - " . MAIN_CHANNEL . "</b>\n\n";
    $message .= "🎬 <b>What you get:</b>\n";
    $message .= "• Latest Bollywood & Hollywood movies\n";
    $message .= "• HD/1080p/720p quality prints\n";
    $message .= "• Daily new uploads\n\n";
    $message .= "📊 <b>Stats:</b> " . ($stats['total_movies'] ?? 0) . "+ movies available";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🍿 Join Channel', 'url' => 'https://t.me/EntertainmentTadka786']]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_serial_channel_info($chat_id) {
    $message = "📺 <b>Serial Channel - " . SERIAL_CHANNEL . "</b>\n\n";
    $message .= "🎬 <b>What you get:</b>\n";
    $message .= "• TV serials & shows\n";
    $message .= "• Daily episodes\n";
    $message .= "• All languages\n\n";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📺 Join Channel', 'url' => 'https://t.me/Entertainment_Tadka_Serial_786']]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_theater_channel_info($chat_id) {
    $message = "🎭 <b>Theater Prints - " . THEATER_CHANNEL . "</b>\n\n";
    $message .= "🎥 <b>What you get:</b>\n";
    $message .= "• Latest theater prints\n";
    $message .= "• HD screen recordings\n";
    $message .= "• Fast uploads after release\n\n";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '🎭 Join Channel', 'url' => 'https://t.me/threater_print_movies']]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_backup_channel_info($chat_id, $user_id) {
    if ($user_id == ADMIN_ID) {
        $message = "🔒 <b>Backup Channel - " . BACKUP_CHANNEL_USERNAME . "</b>\n\n";
        $message .= "🛡️ <b>Purpose:</b> Secure data backups\n";
        $message .= "💾 Daily auto-backup at " . AUTO_BACKUP_HOUR . ":00\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🔒 Join Channel', 'url' => 'https://t.me/ETBackup']]
            ]
        ];
    } else {
        $message = "🔒 <b>Backup Channel</b>\n\nThis is a private admin-only channel.";
        $keyboard = null;
    }
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function show_request_group_info($chat_id) {
    $message = "📥 <b>Request Group - " . REQUEST_GROUP_USERNAME . "</b>\n\n";
    $message .= "🎯 <b>How to request:</b>\n";
    $message .= "1. Join this group\n";
    $message .= "2. Use /request movie_name\n";
    $message .= "3. We'll add within 24 hours\n\n";
    $message .= "🔔 Auto-notification when added!";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '📥 Join Group', 'url' => 'https://t.me/EntertainmentTadka7860']]
        ]
    ];
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}
?>