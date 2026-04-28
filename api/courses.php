<?php
/**
 * API: Courses
 * CRUD operations for courses (Playlists).
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

// ─── GET: List Courses ────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'list') {
    $search  = sanitize($_GET['search'] ?? '');
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 9;
    $offset  = ($page - 1) * $perPage;

    $where  = ['c.is_active = 1'];
    $params = [];

    if ($search !== '') {
        $where[]  = '(c.title LIKE ? OR c.description LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $whereClause = 'WHERE ' . implode(' AND ', $where);

    $countStmt = db()->prepare("SELECT COUNT(*) FROM courses c $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $userId = (int)$_SESSION['user_id'];
    $stmt   = db()->prepare("
        SELECT c.*,
               u.name AS author,
               e.progress,
               e.completed_at,
               (SELECT COUNT(*) FROM course_modules m WHERE m.course_id = c.id) AS module_count,
               (SELECT COUNT(*) FROM course_modules m
                JOIN module_progress mp ON mp.module_id = m.id
                WHERE m.course_id = c.id AND mp.user_id = ?) AS completed_modules
        FROM courses c
        JOIN users u ON u.id = c.created_by
        LEFT JOIN enrollments e ON e.course_id = c.id AND e.user_id = ?
        $whereClause
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ");

    $execParams = array_merge([$userId, $userId], $params, [$perPage, $offset]);
    $stmt->execute($execParams);
    $courses = $stmt->fetchAll();

    foreach ($courses as &$course) {
        $course['created_at_fmt'] = formatDate($course['created_at']);
        if ($course['progress'] !== null) {
            $modCount  = (int)$course['module_count'];
            $compCount = (int)$course['completed_modules'];
            $course['progress'] = $modCount > 0 ? floor(($compCount / $modCount) * 100) : 0;
        }
        // Ensure thumbnail path is absolute or null
        if ($course['thumbnail'] && !str_starts_with($course['thumbnail'], 'http')) {
            $course['thumbnail'] = BASE_URL . '/' . ltrim($course['thumbnail'], '/');
        }
    }

    jsonResponse(true, 'OK', [
        'courses'     => $courses,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => (int)ceil($total / $perPage),
    ]);
}

// ─── All POST routes require CSRF ─────────────────────────────────────────────
if ($method === 'POST') {
    requireCsrf();
}

// ─── POST: Add Course (Admin only) ────────────────────────────────────────────
if ($method === 'POST' && $action === 'add') {
    if (!isAdmin()) {
        jsonResponse(false, 'Access denied.', [], 403);
    }

    $title       = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');

    if (empty($title)) {
        jsonResponse(false, 'Course title is required.', [], 422);
    }
    if (strlen($title) > 200) {
        jsonResponse(false, 'Title must be 200 characters or fewer.', [], 422);
    }

    $thumbnail = null;

    // Handle thumbnail upload if provided
    if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['thumbnail'];

        if ($file['size'] > THUMBNAIL_MAX_SIZE) {
            jsonResponse(false, 'Thumbnail must be 5MB or smaller.', [], 422);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_IMAGE_EXTENSIONS, true)) {
            jsonResponse(false, 'Thumbnail must be JPG, PNG, or WEBP.', [], 422);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ALLOWED_IMAGE_MIME_TYPES, true)) {
            jsonResponse(false, 'Uploaded file is not a valid image.', [], 422);
        }

        $thumbDir = UPLOAD_PATH . 'thumbnails/';
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        $newName = 'thumb_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest    = $thumbDir . $newName;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            jsonResponse(false, 'Failed to save thumbnail. Check server permissions.', [], 500);
        }

        $thumbnail = 'uploads/thumbnails/' . $newName;
    }

    $stmt = db()->prepare("INSERT INTO courses (title, description, thumbnail, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $description, $thumbnail, $_SESSION['user_id']]);
    $newId = db()->lastInsertId();

    jsonResponse(true, 'Course created successfully!', ['course_id' => $newId]);
}

// ─── POST: Update Course Thumbnail (Admin only) ────────────────────────────────
if ($method === 'POST' && $action === 'update_thumbnail') {
    if (!isAdmin()) {
        jsonResponse(false, 'Access denied.', [], 403);
    }

    $courseId = (int)($_POST['course_id'] ?? 0);
    if ($courseId < 1) jsonResponse(false, 'Invalid course ID.', [], 422);

    if (!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(false, 'No valid thumbnail file provided.', [], 422);
    }

    $file = $_FILES['thumbnail'];

    if ($file['size'] > THUMBNAIL_MAX_SIZE) {
        jsonResponse(false, 'Thumbnail must be 5MB or smaller.', [], 422);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTENSIONS, true)) {
        jsonResponse(false, 'Thumbnail must be JPG, PNG, or WEBP.', [], 422);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_MIME_TYPES, true)) {
        jsonResponse(false, 'Uploaded file is not a valid image.', [], 422);
    }

    $thumbDir = UPLOAD_PATH . 'thumbnails/';
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }

    $newName = 'thumb_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest    = $thumbDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(false, 'Failed to save thumbnail.', [], 500);
    }

    $relativePath = 'uploads/thumbnails/' . $newName;

    // Delete old thumbnail file if it exists
    $oldStmt = db()->prepare("SELECT thumbnail FROM courses WHERE id = ?");
    $oldStmt->execute([$courseId]);
    $oldThumb = $oldStmt->fetchColumn();
    if ($oldThumb && file_exists(UPLOAD_PATH . basename($oldThumb))) {
        @unlink(UPLOAD_PATH . 'thumbnails/' . basename($oldThumb));
    }

    db()->prepare("UPDATE courses SET thumbnail = ? WHERE id = ?")->execute([$relativePath, $courseId]);

    jsonResponse(true, 'Thumbnail updated.', ['path' => BASE_URL . '/' . $relativePath]);
}

// ─── POST: Delete Course (Admin only) ────────────────────────────────────────
if ($method === 'POST' && $action === 'delete') {
    if (!isAdmin()) {
        jsonResponse(false, 'Access denied.', [], 403);
    }

    $id = (int)($_POST['course_id'] ?? 0);
    if ($id < 1) {
        jsonResponse(false, 'Invalid course ID.', [], 422);
    }

    db()->prepare("UPDATE courses SET is_active = 0 WHERE id = ?")->execute([$id]);
    jsonResponse(true, 'Course removed successfully.');
}

// ─── POST: Enroll ─────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'enroll') {
    $courseId = (int)($_POST['course_id'] ?? 0);
    $userId   = (int)$_SESSION['user_id'];

    if ($courseId < 1) {
        jsonResponse(false, 'Invalid course ID.', [], 422);
    }

    $stmt = db()->prepare("SELECT id FROM courses WHERE id = ? AND is_active = 1");
    $stmt->execute([$courseId]);
    if (!$stmt->fetch()) {
        jsonResponse(false, 'Course not found.', [], 404);
    }

    $stmt = db()->prepare("INSERT IGNORE INTO enrollments (user_id, course_id) VALUES (?, ?)");
    $stmt->execute([$userId, $courseId]);

    jsonResponse(true, 'Enrolled successfully!');
}

jsonResponse(false, 'Invalid request.', [], 400);
