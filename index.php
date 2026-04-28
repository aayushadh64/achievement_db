<?php
/**
 * Entry Point — Redirect to login or dashboard based on session
 */
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

session_name(SESSION_NAME);
session_start();

if (isLoggedIn()) {
    redirectAbsolute(BASE_URL . (isAdmin() ? '/admin/dashboard.php' : '/user/dashboard.php'));
} else {
    redirectAbsolute(BASE_URL . '/auth/login.php');
}
