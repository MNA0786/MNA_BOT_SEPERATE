# 🎬 Entertainment Tadka Bot

Telegram Movie Bot with Multi-Channel Support

## 📋 Features

- ✅ **Smart Search** - Fuzzy matching with Hindi/English support
- ✅ **Multi-Channel Support** - 6 channels integrated
- ✅ **Auto Channel Scan** - New + old movies auto-add
- ✅ **Pagination** - Browse movies with beautiful UI
- ✅ **Protected Content** - No forward option
- ✅ **Auto Delete Timer** - 300s with progress bar
- ✅ **User Settings** - Personalize your experience
- ✅ **Bulk Send** - SEND ALL button with progress
- ✅ **User Attribution** - "Requested by @username"
- ✅ **Typing Indicators** - With ETA and progress bar
- ✅ **Points System** - Earn points and rank up
- ✅ **Leaderboard** - Top users competition
- ✅ **Auto Backup** - Daily backup to channel
- ✅ **Admin Panel** - Complete control

## 📢 Channels

| Channel | Type | Link |
|---------|------|------|
| 🍿 Main | Public | @EntertainmentTadka786 |
| 📺 Serials | Public | @Entertainment_Tadka_Serial_786 |
| 🎭 Theater | Public | @threater_print_movies |
| 🔒 Backup | Public | @ETBackup |
| 📥 Requests | Public | @EntertainmentTadka7860 |
| 🔐 Private 1 | Private | -1003251791991 |
| 🔐 Private 2 | Private | -1002337293281 |

## 🤖 Commands

### 🔍 Search
- `/search movie` - Search movies
- `/s movie` - Quick search
- Just type name - Auto-search

### 📁 Browse
- `/totalupload` - All movies
- `/latest` - Latest additions
- `/theater` - Theater prints only

### 📝 Requests
- `/request movie` - Request movie
- `/myrequests` - Your requests

### 👤 User
- `/mystats` - Your statistics
- `/leaderboard` - Top users
- `/settings` - Preferences

### 📢 Channels
- `/channels` - All channels
- `/main` - Main channel
- `/theater` - Theater channel

### 👑 Admin
- `/stats` - Bot statistics
- `/broadcast` - Send to all
- `/backup` - Manual backup
- `/scan` - Scan channels
- `/quickadd` - Quick add movie

## 🚀 Deployment

### On Render.com
1. Fork this repository
2. Connect to Render
3. Set `BOT_TOKEN` environment variable
4. Deploy!

### On VPS
```bash
git clone https://github.com/yourusername/entertainment-tadka-bot
cd entertainment-tadka-bot
composer install
chmod -R 777 data/ backups/
php -S 0.0.0.0:8080