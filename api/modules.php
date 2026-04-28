<?php
/**
 * API: Modules
 * CRUD for Course Modules (Videos, Ebooks)
 * GET /api/modules.php?course_id=X
 * POST /api/modules.php?action=add
 * POST /api/modules.php?action=progress
 */

require_once __DIR__ . '/../config/app.php';
session_name(SESSION_NAME);
session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

if (!isLoggedIn()) {
    jsonResponse(false, 'Authentication required.', [], 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($method === 'GET' ? 'list' : 'add');

// ─── GET: List Modules for a Course ──────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $courseId = (int)($_GET['course_id'] ?? 0);
    $userId   = (int)$_SESSION['user_id'];

    if ($courseId < 1) {
        jsonResponse(false, 'Course ID required.', [], 422);
    }

    // Admins see all modules. Users must be enrolled.
    if (!isAdmin()) {
        $check = db()->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
        $check->execute([$userId, $courseId]);
        if (!$check->fetch()) {
            jsonResponse(false, 'You must enroll first.', [], 403);
        }
    }

    $stmt = db()->prepare("
        SELECT m.*, 
               IF(mp.id IS NOT NULL, 1, 0) AS is_completed
        FROM course_modules m
        LEFT JOIN module_progress mp ON mp.module_id = m.id AND mp.user_id = ?
        WHERE m.course_id = ?
        ORDER BY m.order_index ASC, m.id ASC
    ");
    $stmt->execute([$userId, $courseId]);
    $modules = $stmt->fetchAll();

    foreach ($modules as &$mod) {
        if ($mod['type'] === 'youtube') {
            $mod['youtube_id'] = extractYouTubeId($mod['content_data'] ?? '');
        }
    }

    jsonResponse(true, 'OK', ['modules' => $modules]);
}

if ($method === 'POST') {
    requireCsrf();
}

// ─── POST: Add Module (Admin only) ───────────────────────────────────────────
if ($method === 'POST' && $action === 'add') {
    if (!isAdmin()) {
        jsonResponse(false, 'Access denied.', [], 403);
    }

    $courseId    = (int)($_POST['course_id'] ?? 0);
    $title       = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $type        = $_POST['type'] ?? '';

    if ($courseId < 1 || empty($title) || empty($type)) {
        jsonResponse(false, 'Required fields missing.', [], 422);
    }

    $contentData = null;
    if ($type === 'youtube') {
        $rawUrl = trim($_POST['youtube_url'] ?? '');
        if (!extractYouTubeId($rawUrl)) {
            jsonResponse(false, 'Invalid YouTube URL.', [], 422);
        }
        $contentData = $rawUrl;
    } elseif ($type === 'ebook') {
        if (!isset($_FILES['ebook_file']) || $_FILES['ebook_file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(false, 'Valid ebook upload required.', [], 422);
        }
        $contentData = handleFileUpload($_FILES['ebook_file']);
    } elseif ($type === 'quiz') {
        $contentData = null;
    }

    // Get max order index
    $idxStmt = db()->prepare("SELECT IFNULL(MAX(order_index), -1) FROM course_modules WHERE course_id = ?");
    $idxStmt->execute([$courseId]);
    $nextOrder = (int)$idxStmt->fetchColumn() + 1;

    $stmt = db()->prepare("
        INSERT INTO course_modules (course_id, title, description, type, content_data, order_index)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$courseId, $title, $description, $type, $contentData, $nextOrder]);

    jsonResponse(true, 'Module added successfully!', ['module_id' => db()->lastInsertId()]);
}

// ─── POST: Delete Module (Admin only) ────────────────────────────────────────
if ($method === 'POST' && $action === 'delete') {
    if (!isAdmin()) {
        jsonResponse(false, 'Access denied.', [], 403);
    }
    $modId = (int)($_POST['module_id'] ?? 0);
    db()->prepare("DELETE FROM course_modules WHERE id = ?")->execute([$modId]);
    jsonResponse(true, 'Module deleted.');
}

// ─── POST: Mark Module Progress ────────────────────────────────────────────────
if ($method === 'POST' && $action === 'progress') {
    $moduleId = (int)($_POST['module_id'] ?? 0);
    $courseId = (int)($_POST['course_id'] ?? 0);
    $userId   = (int)$_SESSION['user_id'];

    // Insert progress record
    $stmt = db()->prepare("INSERT IGNORE INTO module_progress (user_id, module_id) VALUES (?, ?)");
    $stmt->execute([$userId, $moduleId]);

    // Check if entire course is completed
    $totalStmt = db()->prepare("SELECT COUNT(*) FROM course_modules WHERE course_id = ?");
    $totalStmt->execute([$courseId]);
    $totalModules = (int)$totalStmt->fetchColumn();

    $compStmt = db()->prepare("
        SELECT COUNT(*) FROM course_modules m 
        JOIN module_progress mp ON mp.module_id = m.id 
        WHERE m.course_id = ? AND mp.user_id = ?
    ");
    $compStmt->execute([$courseId, $userId]);
    $completedModules = (int)$compStmt->fetchColumn();

    $percent = $totalModules > 0 ? floor(($completedModules / $totalModules) * 100) : 0;
    
    // Update enrollment progress
    $completedAt = $percent >= 100 ? date('Y-m-d H:i:s') : null;
    $updEnroll = db()->prepare("UPDATE enrollments SET progress = ?, completed_at = ? WHERE user_id = ? AND course_id = ?");
    $updEnroll->execute([$percent, $completedAt, $userId, $courseId]);

    // Trigger achievement logic
    if ($percent >= 100) {
        $cStmt = db()->prepare("SELECT title FROM courses WHERE id = ?");
        $cStmt->execute([$courseId]);
        $c = $cStmt->fetch();
        if ($c) {
            $achieveStmt = db()->prepare("INSERT IGNORE INTO achievements (user_id, title, description) VALUES (?, ?, ?)");
            $achieveStmt->execute([$userId, 'Completed: ' . $c['title'], 'You finished all modules!']);
        }
    }

    jsonResponse(true, 'Progress saved.', ['overall_progress' => $percent]);
}

jsonResponse(false, 'Invalid request.', [], 400);
