<?php
// ==============================
// SCAN OFFSET FILES INITIALIZER
// ==============================
// Run: php scans/scan_offset_initializer.php
// ==============================

// Create scans directory if not exists
if (!file_exists(__DIR__)) {
    mkdir(__DIR__, 0777, true);
}

// All channel IDs
$channels = [
    '-1003181705395' => 245,  // Main Channel - last message ID
    '-1003614546520' => 107,  // Serial Channel
    '-1002831605258' => 61,   // Theater Channel
    '-1002964109368' => 0,    // Backup Channel
    '-1003251791991' => 333,  // Private Channel 1
    '-1002337293281' => 333,  // Private Channel 2
    '-1003083386043' => 0,    // Request Group
];

echo "🔄 Creating scan offset files...\n\n";

foreach ($channels as $channel_id => $last_message) {
    $filename = __DIR__ . '/scan_offset_' . $channel_id . '.txt';
    file_put_contents($filename, $last_message);
    echo "✅ Created: scan_offset_{$channel_id}.txt (Last ID: {$last_message})\n";
}

echo "\n🎉 All scan offset files created successfully!\n";
?>