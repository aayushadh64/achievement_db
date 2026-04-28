<?php
/**
 * Logout
 * Validates CSRF via GET token or just destroys session safely.
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';

session_name(SESSION_NAME);
session_start();

// CSRF check for GET-based logout (token in URL)
$token = $_GET['token'] ?? '';
if (!validateCsrfToken($token)) {
    setFlash('error', 'Invalid logout request.');
    redirectAbsolute(BASE_URL . '/auth/login.php');
}

logoutUser();
setFlash('success', 'You have been logged out successfully.');
redirectAbsolute(BASE_URL . '/auth/login.php');
