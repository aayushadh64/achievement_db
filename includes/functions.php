<?php
/**
 * Core Helper Functions
 * Sanitization, flash messages, response helpers, and utilities.
 */

require_once __DIR__ . '/../config/app.php';

// ─── Output / Response ───────────────────────────────────────────────────────

/**
 * Send a JSON response and terminate.
 */
function jsonResponse(bool $success, string $message, array $data = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

/**
 * Redirect to a URL and terminate.
 */
function redirect(string $path): never
{
    header('Location: ' . BASE_URL . '/' . ltrim($path, '/'));
    exit;
}

/**
 * Redirect to an absolute URL and terminate.
 */
function redirectAbsolute(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ─── Sanitization ────────────────────────────────────────────────────────────

/**
 * Sanitize a string for safe HTML output (prevents XSS).
 */
function sanitize(string $input): string
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Sanitize and validate an email address.
 */
function sanitizeEmail(string $email): string|false
{
    $email = trim($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? strtolower($email) : false;
}

/**
 * Sanitize a URL (YouTube links etc.)
 */
function sanitizeUrl(string $url): string|false
{
    $url = trim($url);
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
}

// ─── Flash Messages ──────────────────────────────────────────────────────────

/**
 * Set a flash message in session.
 * Types: success | error | warning | info
 */
function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Get and clear the flash message from session.
 * Returns null if no flash message exists.
 */
function getFlash(): ?array
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─── Auth Helpers ─────────────────────────────────────────────────────────────

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin(): bool
{
    return isLoggedIn() && ($_SESSION['role'] ?? '') === 'admin';
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to continue.');
        redirectAbsolute(BASE_URL . '/auth/login.php');
    }
}

function requireAdmin(): void
{
    requireLogin();
    if (!isAdmin()) {
        setFlash('error', 'Access denied. Admins only.');
        redirectAbsolute(BASE_URL . '/user/dashboard.php');
    }
}

function currentUser(): array
{
    return [
        'id'   => $_SESSION['user_id'] ?? null,
        'name' => $_SESSION['user_name'] ?? 'Guest',
        'role' => $_SESSION['role'] ?? 'user',
    ];
}

// ─── Rate Limiting ────────────────────────────────────────────────────────────

/**
 * Check and enforce rate limiting for an action by IP.
 * Returns true if action is ALLOWED, false if BLOCKED.
 */
function checkRateLimit(string $action): bool
{
    $ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $pdo    = db();
    $window = date('Y-m-d H:i:s', time() - RATE_LIMIT_WINDOW);

    // Clean expired windows
    $pdo->prepare("DELETE FROM rate_limits WHERE window_start < ?")->execute([$window]);

    // Check current attempts
    $stmt = $pdo->prepare(
        "SELECT attempts FROM rate_limits WHERE ip_address = ? AND action = ?"
    );
    $stmt->execute([$ip, $action]);
    $row = $stmt->fetch();

    if ($row) {
        if ($row['attempts'] >= RATE_LIMIT_ATTEMPTS) {
            return false; // BLOCKED
        }
        // Increment
        $pdo->prepare(
            "UPDATE rate_limits SET attempts = attempts + 1 WHERE ip_address = ? AND action = ?"
        )->execute([$ip, $action]);
    } else {
        // First attempt
        $pdo->prepare(
            "INSERT INTO rate_limits (ip_address, action) VALUES (?, ?)"
        )->execute([$ip, $action]);
    }

    return true; // ALLOWED
}

// ─── File Upload ──────────────────────────────────────────────────────────────

/**
 * Validate and move an uploaded file.
 * Returns the saved filename on success, or throws RuntimeException on failure.
 */
function handleFileUpload(array $file): string
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed with error code: ' . $file['error']);
    }

    if ($file['size'] > UPLOAD_MAX_SIZE) {
        throw new RuntimeException('File size exceeds the maximum allowed size of ' . (UPLOAD_MAX_SIZE / 1024 / 1024) . ' MB.');
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
        throw new RuntimeException('Invalid file type. Only PDF and EPUB files are allowed.');
    }

    // Validate MIME type using finfo
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
        throw new RuntimeException('File MIME type is not allowed.');
    }

    // Generate a unique, safe filename
    $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
    $dest     = UPLOAD_PATH . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Could not save the uploaded file.');
    }

    return $safeName;
}

// ─── YouTube ──────────────────────────────────────────────────────────────────

/**
 * Extract YouTube video ID from a full URL.
 */
function extractYouTubeId(string $url): ?string
{
    preg_match(
        '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
        $url,
        $matches
    );
    return $matches[1] ?? null;
}

// ─── Misc ─────────────────────────────────────────────────────────────────────

/**
 * Generate a random token (hex string).
 */
function generateToken(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Format a datetime string for display.
 */
function formatDate(string $datetime, string $format = 'M j, Y'): string
{
    return (new DateTime($datetime))->format($format);
}
