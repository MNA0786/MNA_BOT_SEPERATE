<?php
// ==============================
// CONFIGURATION WITH ENV SUPPORT
// ==============================

// Load .env file if exists
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Helper function to get env
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    return $value;
}

// Bot Token
if (!env('BOT_TOKEN')) {
    die("❌ BOT_TOKEN not set in .env file");
}
define('BOT_TOKEN', env('BOT_TOKEN'));

// API Credentials
define('API_ID', env('API_ID', '21944581'));
define('API_HASH', env('API_HASH', '7b1c174a5cd3466e25a976c39a791737'));
define('BOT_USERNAME', env('BOT_USERNAME', '@EntertainmentTadkaBot'));
define('BOT_ID', env('BOT_ID', '8315381064'));
define('ADMIN_ID', (int)env('ADMIN_ID', '1080317415'));

// Channel Configurations
define('MAIN_CHANNEL', env('MAIN_CHANNEL', '@EntertainmentTadka786'));
define('MAIN_CHANNEL_ID', env('MAIN_CHANNEL_ID', '-1003181705395'));
define('SERIAL_CHANNEL', env('SERIAL_CHANNEL', '@Entertainment_Tadka_Serial_786'));
define('SERIAL_CHANNEL_ID', env('SERIAL_CHANNEL_ID', '-1003614546520'));
define('THEATER_CHANNEL', env('THEATER_CHANNEL', '@threater_print_movies'));
define('THEATER_CHANNEL_ID', env('THEATER_CHANNEL_ID', '-1002831605258'));
define('BACKUP_CHANNEL_USERNAME', env('BACKUP_CHANNEL_USERNAME', '@ETBackup'));
define('BACKUP_CHANNEL_ID', env('BACKUP_CHANNEL_ID', '-1002964109368'));
define('REQUEST_GROUP_USERNAME', env('REQUEST_GROUP_USERNAME', '@EntertainmentTadka7860'));
define('REQUEST_GROUP_ID', env('REQUEST_GROUP_ID', '-1003083386043'));
define('PRIVATE_CHANNEL_1_ID', env('PRIVATE_CHANNEL_1_ID', '-1003251791991'));
define('PRIVATE_CHANNEL_2_ID', env('PRIVATE_CHANNEL_2_ID', '-1002337293281'));

// File Paths
define('CSV_FILE', __DIR__ . '/data/movies.csv');
define('USERS_FILE', __DIR__ . '/data/users.json');
define('STATS_FILE', __DIR__ . '/data/bot_stats.json');
define('REQUEST_FILE', __DIR__ . '/data/movie_requests.json');
define('SETTINGS_FILE', __DIR__ . '/data/user_settings.json');
define('BACKUP_DIR', __DIR__ . '/backups/');
define('LOG_FILE', __DIR__ . '/bot_activity.log');

// Bot Settings
define('CACHE_EXPIRY', 300);
define('ITEMS_PER_PAGE', 5);
define('MAX_SEARCH_RESULTS', 15);
define('DAILY_REQUEST_LIMIT', 5);
define('AUTO_BACKUP_HOUR', env('AUTO_BACKUP_HOUR', '03'));
define('MAX_PAGES_TO_SHOW', 7);
define('PAGINATION_CACHE_TIMEOUT', 60);
define('PREVIEW_ITEMS', 3);
define('BATCH_SIZE', 5);
define('AUTO_DELETE_TIME', 300);

// Public Channels List
define('PUBLIC_CHANNELS', json_encode([
    ['name' => '🍿 Main Channel', 'username' => '@EntertainmentTadka786', 'link' => 'https://t.me/EntertainmentTadka786'],
    ['name' => '📺 Serial Channel', 'username' => '@Entertainment_Tadka_Serial_786', 'link' => 'https://t.me/Entertainment_Tadka_Serial_786'],
    ['name' => '🎭 Theater Prints', 'username' => '@threater_print_movies', 'link' => 'https://t.me/threater_print_movies'],
    ['name' => '🔒 Backup Channel', 'username' => '@ETBackup', 'link' => 'https://t.me/ETBackup'],
    ['name' => '📥 Request Group', 'username' => '@EntertainmentTadka7860', 'link' => 'https://t.me/EntertainmentTadka7860']
]));

// Private Channels List
define('PRIVATE_CHANNELS', json_encode([
    ['id' => PRIVATE_CHANNEL_1_ID, 'name' => 'Private Channel 1'],
    ['id' => PRIVATE_CHANNEL_2_ID, 'name' => 'Private Channel 2']
]));

// Maintenance Mode
$MAINTENANCE_MODE = env('MAINTENANCE_MODE', 'false') === 'true';
$MAINTENANCE_MESSAGE = "🛠️ <b>Bot Under Maintenance</b>\n\nWe'll be back soon!";

// Global Variables
$movie_messages = array();
$movie_cache = array();
$waiting_users = array();
$user_sessions = array();
$user_pagination_sessions = array();
$user_quickadd_sessions = array();
$delete_timers = array();

// Timezone
date_default_timezone_set(env('TIMEZONE', 'Asia/Kolkata'));
?>