<?php
// ==============================
// USER SETTINGS PANEL
// ==============================

require_once 'config.php';
require_once 'telegram.php';
require_once 'users.php';

// Default settings
define('DEFAULT_SETTINGS', json_encode([
    'language' => 'hindi',
    'theme' => 'dark',
    'auto_delete' => true,
    'delete_time' => 300,
    'protected_content' => true,
    'notifications' => true,
    'forward_header' => 'auto', // auto, always, never
    'show_channel_name' => true
]));

function get_user_settings($user_id) {
    $settings_data = json_decode(file_get_contents(SETTINGS_FILE), true);
    
    if (!isset($settings_data['user_settings'][$user_id])) {
        // Load default settings
        $settings_data['user_settings'][$user_id] = json_decode(DEFAULT_SETTINGS, true);
        file_put_contents(SETTINGS_FILE, json_encode($settings_data, JSON_PRETTY_PRINT));
    }
    
    return $settings_data['user_settings'][$user_id];
}

function update_user_setting($user_id, $key, $value) {
    $settings_data = json_decode(file_get_contents(SETTINGS_FILE), true);
    
    if (!isset($settings_data['user_settings'][$user_id])) {
        $settings_data['user_settings'][$user_id] = json_decode(DEFAULT_SETTINGS, true);
    }
    
    $settings_data['user_settings'][$user_id][$key] = $value;
    file_put_contents(SETTINGS_FILE, json_encode($settings_data, JSON_PRETTY_PRINT));
    
    return true;
}

function reset_user_settings($user_id) {
    $settings_data = json_decode(file_get_contents(SETTINGS_FILE), true);
    $settings_data['user_settings'][$user_id] = json_decode(DEFAULT_SETTINGS, true);
    file_put_contents(SETTINGS_FILE, json_encode($settings_data, JSON_PRETTY_PRINT));
    
    return true;
}

// ==============================
// SETTINGS PANEL UI
// ==============================

function show_settings_panel($chat_id, $user_id) {
    $settings = get_user_settings($user_id);
    $username = get_username_by_id($user_id);
    
    $message = "⚙️ <b>Personalize Your Settings</b>\n\n";
    $message .= "👤 User: @" . $username . "\n\n";
    
    $message .= "🎯 <b>Current Settings:</b>\n";
    $message .= "• 🌙 Theme: <b>" . ucfirst($settings['theme']) . " Mode</b>\n";
    $message .= "• 🗣️ Language: <b>" . ucfirst($settings['language']) . "</b>\n";
    $message .= "• ⏱️ Auto-Delete: <b>" . ($settings['auto_delete'] ? 'ON (' . format_time_eta($settings['delete_time']) . ')' : 'OFF') . "</b>\n";
    $message .= "• 🔒 Protected: <b>" . ($settings['protected_content'] ? 'ON' : 'OFF') . "</b>\n";
    $message .= "• 📢 Notifications: <b>" . ($settings['notifications'] ? 'ON' : 'OFF') . "</b>\n";
    $message .= "• 📋 Forward Header: <b>" . ucfirst($settings['forward_header']) . "</b>\n\n";
    
    $message .= "👇 Click to customize:";
    
    $keyboard = build_settings_keyboard($settings);
    
    sendMessage($chat_id, $message, $keyboard, 'HTML');
}

function build_settings_keyboard($settings) {
    $keyboard = ['inline_keyboard' => []];
    
    // Theme row
    $theme_btn = ['text' => '🎨 Theme: ' . ucfirst($settings['theme']), 'callback_data' => 'settings_theme'];
    $lang_btn = ['text' => '🗣️ Language: ' . ucfirst($settings['language']), 'callback_data' => 'settings_lang'];
    $keyboard['inline_keyboard'][] = [$theme_btn, $lang_btn];
    
    // Auto-delete row
    $delete_status = $settings['auto_delete'] ? 'ON (' . format_time_eta($settings['delete_time']) . ')' : 'OFF';
    $keyboard['inline_keyboard'][] = [['text' => '⏱️ Auto-Delete: ' . $delete_status, 'callback_data' => 'settings_delete']];
    
    // Timer options (if auto-delete is on)
    if ($settings['auto_delete']) {
        $keyboard['inline_keyboard'][] = [
            ['text' => '30s', 'callback_data' => 'timer_30'],
            ['text' => '1m', 'callback_data' => 'timer_60'],
            ['text' => '2m', 'callback_data' => 'timer_120'],
            ['text' => '5m', 'callback_data' => 'timer_300'],
            ['text' => '10m', 'callback_data' => 'timer_600']
        ];
    }
    
    // Toggles row
    $keyboard['inline_keyboard'][] = [
        ['text' => '🔒 Protected: ' . ($settings['protected_content'] ? 'ON' : 'OFF'), 'callback_data' => 'settings_protected'],
        ['text' => '📢 Notifications: ' . ($settings['notifications'] ? 'ON' : 'OFF'), 'callback_data' => 'settings_notify']
    ];
    
    // Forward header options
    $keyboard['inline_keyboard'][] = [['text' => '📋 Forward Header: ' . ucfirst($settings['forward_header']), 'callback_data' => 'settings_forward']];
    
    // Action buttons
    $keyboard['inline_keyboard'][] = [
        ['text' => '🔄 Reset to Default', 'callback_data' => 'settings_reset'],
        ['text' => '❌ Close', 'callback_data' => 'close_settings']
    ];
    
    return $keyboard;
}

// ==============================
// SETTINGS HANDLERS
// ==============================

function handle_settings_callback($chat_id, $user_id, $data, $message_id) {
    $settings = get_user_settings($user_id);
    
    switch ($data) {
        case 'settings_theme':
            $new_theme = $settings['theme'] == 'dark' ? 'light' : 'dark';
            update_user_setting($user_id, 'theme', $new_theme);
            answerCallbackQuery($data['id'], "Theme changed to $new_theme");
            show_settings_panel($chat_id, $user_id);
            deleteMessage($chat_id, $message_id);
            break;
            
        case 'settings_lang':
            $new_lang = $settings['language'] == 'hindi' ? 'english' : 'hindi';
            update_user_setting($user_id, 'language', $new_lang);
            answerCallbackQuery($data['id'], "Language changed to $new_lang");
            show_settings_panel($chat_id, $user_id);
            deleteMessage($chat_id, $message_id);
            break;
            
        case 'settings_delete':
            $new_status = !$settings['auto_delete'];
            update_user_setting($user_id, 'auto_delete', $new_status);
            answerCallbackQuery($data['id'], "Auto-delete " . ($new_status ? 'enabled' : 'disabled'));
            show_settings_panel($chat_id, $user_id);
            deleteMessage($chat_id, $message_id);
            break;
            
        case 'settings_protected':
            $new_status = !$settings['protected_content'];
            update_user_setting($user_id, 'protected_content', $new_status);
            answerCallbackQuery($data['id'], "Protected content " . ($new_status ? 'enabled' : 'disabled'));
            show_settings_panel($chat_id, $user_id);
            deleteMessage($chat_id, $message_id);
            break;
            
        case 'settings_notify':
            $new_status = !$settings['notifications'];
            update_user_setting($user_id, 'notifications', $new_status);
            answerCallbackQuery($data['id'], "Notifications " . ($new_status ? 'enabled' : 'disabled'));
            show_settings_panel($chat_id, $user_id);
            deleteMessage($chat_id, $message_id);
            break;
            
        case 'settings_forward':
            $options = ['auto', 'always', 'never'];
            $current_index = array_search($settings['forward_header'], $options);
            $new_index = ($current_index + 1) % 3;
            $new_value = $options[$new_index];
            update_user_setting($user_id, 'forward_header', $new_value);
            answerCallbackQuery($data['id'], "Forward header: $new_value");
            show_settings_panel($chat_id, $user_id);
            deleteMessage($chat_id, $message_id);
            break;
            
        case 'settings_reset':
            reset_user_settings($user_id);
            answerCallbackQuery($data['id'], "Settings reset to default!");
            show_settings_panel($chat_id, $user_id);
            deleteMessage($chat_id, $message_id);
            break;
            
        case substr($data, 0, 6) == 'timer_':
            $seconds = intval(substr($data, 6));
            update_user_setting($user_id, 'delete_time', $seconds);
            answerCallbackQuery($data['id'], "Timer set to " . format_time_eta($seconds));
            show_settings_panel($chat_id, $user_id);
            deleteMessage($chat_id, $message_id);
            break;
            
        case 'close_settings':
            deleteMessage($chat_id, $message_id);
            answerCallbackQuery($data['id'], "Settings closed");
            break;
    }
}
?>