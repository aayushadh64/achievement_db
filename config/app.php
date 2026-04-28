<?php
/**
 * Application Configuration
 * Central constants and settings for the Achievement System
 */

// ─── Environment ────────────────────────────────────────────────────────────
define('DEBUG_MODE', true);         // Set true during local development only
define('APP_NAME', 'AchieveHub');
define('APP_VERSION', '2.1.0');
define('BASE_URL', '/temporary/achievement_db'); // ← Update for your server path

// ─── Database ───────────────────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'achievement_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ─── Session ─────────────────────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600);    // 1 hour in seconds
define('SESSION_NAME', 'ach_sess');

// ─── Security ────────────────────────────────────────────────────────────────
define('CSRF_TOKEN_LENGTH', 32);
define('RATE_LIMIT_ATTEMPTS', 5);    // Max login attempts
define('RATE_LIMIT_WINDOW', 300);    // 5-minute window in seconds

// ─── File Uploads (Ebooks + Thumbnails) ──────────────────────────────────────
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // 10 MB for ebooks

// Ebook types
define('ALLOWED_EXTENSIONS', ['pdf', 'epub']);
define('ALLOWED_MIME_TYPES', ['application/pdf', 'application/epub+zip']);

// Image thumbnail types
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
define('ALLOWED_IMAGE_MIME_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('THUMBNAIL_MAX_SIZE', 5 * 1024 * 1024); // 5 MB for images

// ─── Error Handling ───────────────────────────────────────────────────────────
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// ─── Timezone ─────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Kathmandu');
