#!/bin/bash
# ==============================
# RENDER BUILD SCRIPT
# ==============================

echo "Starting build process..."

# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Create necessary directories
mkdir -p data backups logs scans

# Create log files if they don't exist
touch bot_activity.log
touch logs/error.log

# Set permissions
chmod -R 777 data backups logs scans
chmod 666 bot_activity.log
chmod 666 logs/error.log

# Create initial files if they don't exist
if [ ! -f data/movies.csv ]; then
    echo "movie_name,message_id,date,video_path,quality,size,language,channel_type,channel_id,channel_username" > data/movies.csv
fi

if [ ! -f data/users.json ]; then
    echo '{"users":{},"total_requests":0,"message_logs":[]}' > data/users.json
fi

if [ ! -f data/bot_stats.json ]; then
    echo '{"total_movies":0,"total_users":0,"total_searches":0,"total_downloads":0,"successful_searches":0,"failed_searches":0,"daily_activity":[],"last_updated":"'$(date -u +"%Y-%m-%d %H:%M:%S")'"}' > data/bot_stats.json
fi

if [ ! -f data/movie_requests.json ]; then
    echo '{"requests":[],"pending_approval":[],"completed_requests":[],"user_request_count":[]}' > data/movie_requests.json
fi

if [ ! -f data/user_settings.json ]; then
    echo '{"user_settings":[]}' > data/user_settings.json
fi

if [ ! -f data/tasks.json ]; then
    echo '{"pending_tasks":[],"completed_tasks":[],"failed_tasks":[]}' > data/tasks.json
fi

# Create scan offset files
php -r "
\$channels = [
    '-1003181705395' => 245,
    '-1003614546520' => 107,
    '-1002831605258' => 61,
    '-1002964109368' => 0,
    '-1003251791991' => 333,
    '-1002337293281' => 333,
    '-1003083386043' => 0,
];
foreach (\$channels as \$id => \$last) {
    file_put_contents('scans/scan_offset_' . \$id . '.txt', \$last);
}
"

echo "Build completed successfully!"
