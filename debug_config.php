<?php
// ==============================
// DEBUG CONFIG - Check if config loads properly
// ==============================

echo "<h1>🔍 Config Debug</h1>";

// Try to load config
echo "<h2>Loading config.php...</h2>";
require_once 'config.php';
echo "✅ config.php loaded<br>";

// Check BOT_TOKEN
echo "<h2>BOT_TOKEN Status:</h2>";
if (defined('BOT_TOKEN')) {
    echo "✅ BOT_TOKEN is defined<br>";
    echo "Token starts with: " . substr(BOT_TOKEN, 0, 15) . "...<br>";
} else {
    echo "❌ BOT_TOKEN NOT defined!<br>";
}

// Check env function
echo "<h2>env() function test:</h2>";
echo "env('BOT_TOKEN'): " . (env('BOT_TOKEN') ? substr(env('BOT_TOKEN'), 0, 15) . "..." : "❌ Not found") . "<br>";
echo "env('ADMIN_ID'): " . (env('ADMIN_ID') ?: "❌ Not found") . "<br>";

// Check getenv directly
echo "<h2>getenv() direct test:</h2>";
echo "getenv('BOT_TOKEN'): " . (getenv('BOT_TOKEN') ? substr(getenv('BOT_TOKEN'), 0, 15) . "..." : "❌ Not found") . "<br>";

// Check $_ENV
echo "<h2>\$_ENV superglobal:</h2>";
echo "isset(\$_ENV['BOT_TOKEN']): " . (isset($_ENV['BOT_TOKEN']) ? "✅ Yes" : "❌ No") . "<br>";

// Check $_SERVER
echo "<h2>\$_SERVER superglobal:</h2>";
echo "isset(\$_SERVER['BOT_TOKEN']): " . (isset($_SERVER['BOT_TOKEN']) ? "✅ Yes" : "❌ No") . "<br>";

// Show all environment variables (safe version - hide values)
echo "<h2>All Environment Variables (keys only):</h2>";
echo "<pre>";
$env_vars = array_merge(getenv(), $_ENV, $_SERVER);
$keys = array_keys($env_vars);
sort($keys);
foreach ($keys as $key) {
    if (strpos($key, 'BOT') !== false || strpos($key, 'TOKEN') !== false || strpos($key, 'ADMIN') !== false) {
        echo "$key: [HIDDEN]<br>";
    }
}
echo "</pre>";

// Test sendMessage function
echo "<h2>sendMessage() function test:</h2>";
if (function_exists('sendMessage')) {
    echo "✅ sendMessage() exists<br>";
    
    // Try to send test message
    if (defined('ADMIN_ID')) {
        echo "Attempting to send test message to ADMIN_ID: " . ADMIN_ID . "<br>";
        
        $test_result = @sendMessage(ADMIN_ID, "🧪 Test from debug_config at " . date('H:i:s'));
        
        if ($test_result && isset($test_result['ok']) && $test_result['ok']) {
            echo "✅ Test message sent! Check Telegram.<br>";
        } else {
            echo "❌ Failed to send message<br>";
            echo "<pre>";
            print_r($test_result);
            echo "</pre>";
        }
    }
} else {
    echo "❌ sendMessage() not found!<br>";
}

// List included files
echo "<h2>Files loaded:</h2>";
echo "<pre>";
print_r(get_included_files());
echo "</pre>";
?>
