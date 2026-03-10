<?php
// ==============================
// AUTO SCAN WITH OFFSET SUPPORT
// ==============================

require_once 'config.php';
require_once 'database.php';

function get_last_scanned_id($channel_id) {
    $file = __DIR__ . '/scans/scan_offset_' . $channel_id . '.txt';
    if (file_exists($file)) {
        return (int)file_get_contents($file);
    }
    return 0;
}

function update_last_scanned_id($channel_id, $message_id) {
    $file = __DIR__ . '/scans/scan_offset_' . $channel_id . '.txt';
    file_put_contents($file, $message_id);
}

function scan_channel($channel_id, $channel_type, $limit = 100) {
    $last_id = get_last_scanned_id($channel_id);
    $new_messages = 0;
    
    echo "🔍 Scanning {$channel_type} (ID: {$channel_id}) from message {$last_id}...\n";
    
    // Simulated scanning (actual implementation depends on Telegram API)
    // In real implementation, you would fetch messages from Telegram
    
    $new_last_id = $last_id + $new_messages;
    update_last_scanned_id($channel_id, $new_last_id);
    
    echo "✅ Found {$new_messages} new messages. Last ID now: {$new_last_id}\n";
    
    return $new_messages;
}

function scan_all_channels() {
    $channels = [
        ['id' => MAIN_CHANNEL_ID, 'type' => 'main'],
        ['id' => SERIAL_CHANNEL_ID, 'type' => 'serial'],
        ['id' => THEATER_CHANNEL_ID, 'type' => 'theater'],
        ['id' => BACKUP_CHANNEL_ID, 'type' => 'backup'],
        ['id' => PRIVATE_CHANNEL_1_ID, 'type' => 'private1'],
        ['id' => PRIVATE_CHANNEL_2_ID, 'type' => 'private2'],
    ];
    
    $total = 0;
    foreach ($channels as $channel) {
        $found = scan_channel($channel['id'], $channel['type']);
        $total += $found;
        sleep(2); // Rate limiting
    }
    
    echo "📊 Total new messages: {$total}\n";
    return $total;
}

// If called directly
if (php_sapi_name() === 'cli') {
    if ($argc > 1 && $argv[1] === 'scan') {
        scan_all_channels();
    }
}
?>