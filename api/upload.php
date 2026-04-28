<?php
/**
 * API: Upload Handler
 * Handles image thumbnail uploads for courses/modules.
 * POST /api/upload.php?type=thumbnail
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', [], 405);
}

requireCsrf();

$type = $_GET['type'] ?? 'thumbnail';

if ($type === 'thumbnail') {
    if (!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['thumbnail']['error'] ?? -1;
        $errMsg = match($errCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File is too large.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            default => 'Upload failed (error code: ' . $errCode . ').',
        };
        jsonResponse(false, $errMsg, [], 422);
    }

    $file = $_FILES['thumbnail'];

    // Validate file size
    if ($file['size'] > THUMBNAIL_MAX_SIZE) {
        jsonResponse(false, 'Image must be 5MB or smaller.', [], 422);
    }

    // Validate extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_EXTENSIONS, true)) {
        jsonResponse(false, 'Only JPG, PNG, and WEBP images are allowed.', [], 422);
    }

    // Validate MIME type via finfo (not just the reported type)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, ALLOWED_IMAGE_MIME_TYPES, true)) {
        jsonResponse(false, 'File content does not match an allowed image type.', [], 422);
    }

    // Make sure uploads/thumbnails directory exists
    $thumbDir = UPLOAD_PATH . 'thumbnails/';
    if (!is_dir($thumbDir)) {
        mkdir($thumbDir, 0755, true);
    }

    // Generate unique filename to avoid collisions
    $newName = 'thumb_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $thumbDir . $newName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        jsonResponse(false, 'Failed to save uploaded file. Check server permissions.', [], 500);
    }

    // Relative path to store in DB
    $relativePath = 'uploads/thumbnails/' . $newName;

    jsonResponse(true, 'Thumbnail uploaded successfully.', ['path' => $relativePath]);
}

jsonResponse(false, 'Unknown upload type.', [], 400);
