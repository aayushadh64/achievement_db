<?php
/**
 * API: Stats (Admin Dashboard)
 * Returns counts and chart data for the admin statistics panel.
 * GET /api/stats.php
 */

require_once __DIR__ . '/../config/app.php';
session_name(SESSION_NAME);
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Authentication required.', [], 401);
}

if (!isAdmin()) {
    jsonResponse(false, 'Access denied.', [], 403);
}

$pdo = db();

// ─── Counts ───────────────────────────────────────────────────────────────────
$totalUsers       = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalCourses     = (int)$pdo->query("SELECT COUNT(*) FROM courses WHERE is_active = 1")->fetchColumn();
$totalEnrollments = (int)$pdo->query("SELECT COUNT(*) FROM enrollments")->fetchColumn();
$totalCompleted   = (int)$pdo->query("SELECT COUNT(*) FROM enrollments WHERE progress = 100")->fetchColumn();

// ─── Enrollments per day (last 7 days) ───────────────────────────────────────
$stmt = $pdo->query("
    SELECT DATE(enrolled_at) AS day, COUNT(*) AS count
    FROM enrollments
    WHERE enrolled_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY day
    ORDER BY day ASC
");
$enrollmentTrend = $stmt->fetchAll();

// ─── Top Courses by Enrollments ───────────────────────────────────────────────
$stmt = $pdo->query("
    SELECT c.title, COUNT(e.id) AS enrollments
    FROM courses c
    LEFT JOIN enrollments e ON e.course_id = c.id
    WHERE c.is_active = 1
    GROUP BY c.id
    ORDER BY enrollments DESC
    LIMIT 5
");
$topCourses = $stmt->fetchAll();

// ─── Recent Users ─────────────────────────────────────────────────────────────
$stmt = $pdo->query("
    SELECT name, email, created_at
    FROM users
    ORDER BY created_at DESC
    LIMIT 5
");
$recentUsers = $stmt->fetchAll();

// ─── Course Type Split ────────────────────────────────────────────────────────
$courseTypes = [];

jsonResponse(true, 'OK', [
    'stats' => [
        'total_users'       => $totalUsers,
        'total_courses'     => $totalCourses,
        'total_enrollments' => $totalEnrollments,
        'total_completed'   => $totalCompleted,
    ],
    'enrollment_trend' => $enrollmentTrend,
    'top_courses'      => $topCourses,
    'course_types'     => $courseTypes,
    'recent_users'     => $recentUsers,
]);
