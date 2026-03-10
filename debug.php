<?php
// ==============================
// DEBUG FILE - Check configuration
// ==============================

echo "<h1>🔍 Bot Debug Info</h1>";

// Check if config.php loads
echo "<h2>1. Loading config.php...</h2>";
if (file_exists('config.php')) {
    echo "✅ config.php exists<br>";
    require_once 'config.php';
    echo "✅ config.php loaded<br>";
} else {
    echo "❌ config.php NOT found!<br>";
}

// Check BOT_TOKEN
echo "<h2>2. BOT_TOKEN Status:</h2>";
if (defined('BOT_TOKEN')) {
    echo "✅ BOT_TOKEN defined<br>";
    echo "Token: " . substr(BOT_TOKEN, 0, 10) . "...[hidden]<br>";
} else {
    echo "❌ BOT_TOKEN NOT defined!<br>";
    
    // Check environment
    echo "<h3>Environment Variables:</h3>";
    echo "getenv('BOT_TOKEN'): " . (getenv('BOT_TOKEN') ? "✅ Found" : "❌ Not found") . "<br>";
    echo "getenv('ADMIN_ID'): " . (getenv('ADMIN_ID') ?: "❌ Not found") . "<br>";
    echo "\$_ENV['BOT_TOKEN']: " . (isset($_ENV['BOT_TOKEN']) ? "✅ Found" : "❌ Not found") . "<br>";
}

// Test sendMessage
echo "<h2>3. Testing sendMessage function:</h2>";
if (function_exists('sendMessage')) {
    echo "✅ sendMessage() function exists<br>";
    
    // Try to send a test message to admin
    if (defined('ADMIN_ID') && defined('BOT_TOKEN')) {
        echo "Attempting to send test message to ADMIN_ID: " . ADMIN_ID . "<br>";
        
        $test_result = sendMessage(ADMIN_ID, "🔧 Test message from debug.php at " . date('Y-m-d H:i:s'));
        
        if ($test_result && isset($test_result['ok']) && $test_result['ok']) {
            echo "✅ Test message sent successfully!<br>";
        } else {
            echo "❌ Failed to send test message<br>";
            echo "<pre>";
            print_r($test_result);
            echo "</pre>";
        }
    }
} else {
    echo "❌ sendMessage() function NOT found!<br>";
}

// Show loaded files
echo "<h2>4. Loaded Files:</h2>";
echo "<pre>";
print_r(get_included_files());
echo "</pre>";

// PHP Info
echo "<h2>5. PHP Info:</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "s<br>";
?>
