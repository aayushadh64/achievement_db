<?php
/**
 * API: Authentication
 * Handles login and registration via AJAX (returns JSON).
 * POST /api/auth.php?action=login|register|logout
 */

require_once __DIR__ . '/../config/app.php';
session_name(SESSION_NAME);
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

// Parse JSON body if Content-Type is application/json
$body = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
} else {
    $body = $_POST;
}

$action = trim($body['action'] ?? $_GET['action'] ?? '');

// Validate CSRF for all actions
requireCsrf();

// ─── Dispatch ────────────────────────────────────────────────────────────────
switch ($action) {

    case 'login':
        // Rate limit by IP
        if (!checkRateLimit('login')) {
            jsonResponse(false, 'Too many login attempts. Please wait 5 minutes before trying again.', [], 429);
        }

        $email    = $body['email']    ?? '';
        $password = $body['password'] ?? '';

        $result = loginUser($email, $password);

        if (is_array($result)) {
            // Success
            $redirect = $result['role'] === 'admin'
                ? BASE_URL . '/admin/dashboard.php'
                : BASE_URL . '/user/dashboard.php';

            jsonResponse(true, 'Welcome back, ' . htmlspecialchars($result['name']) . '!', [
                'redirect' => $redirect,
                'role'     => $result['role'],
            ]);
        } else {
            jsonResponse(false, $result, [], 401);
        }

    case 'register':
        if (!checkRateLimit('register')) {
            jsonResponse(false, 'Too many registration attempts. Please try again later.', [], 429);
        }

        $name            = $body['name']             ?? '';
        $username        = $body['username']         ?? '';
        $email           = $body['email']            ?? '';
        $password        = $body['password']         ?? '';
        $confirmPassword = $body['confirm_password'] ?? '';

        $result = registerUser($name, $username, $email, $password, $confirmPassword);

        if ($result === true) {
            jsonResponse(true, 'Account created! You can now log in.', [
                'redirect' => BASE_URL . '/auth/login.php'
            ]);
        } else {
            jsonResponse(false, $result, [], 422);
        }

    case 'logout':
        logoutUser();
        jsonResponse(true, 'Logged out successfully.', [
            'redirect' => BASE_URL . '/auth/login.php'
        ]);

    default:
        jsonResponse(false, 'Unknown action.', [], 400);
}
