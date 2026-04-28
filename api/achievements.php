<?php
/**
 * API: Achievements
 * Returns achievements for the logged-in user.
 */
require_once __DIR__ . '/../config/app.php';
session_name(SESSION_NAME);
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Authentication required.', [], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

$userId = (int)$_SESSION['user_id'];
$stmt = db()->prepare("
    SELECT title, description, badge_icon AS icon, DATE_FORMAT(earned_at, '%b %d, %Y') AS date
    FROM achievements
    WHERE user_id = ?
    ORDER BY earned_at DESC
    LIMIT 20
");
$stmt->execute([$userId]);
$achievements = $stmt->fetchAll();

jsonResponse(true, 'OK', ['achievements' => $achievements]);
