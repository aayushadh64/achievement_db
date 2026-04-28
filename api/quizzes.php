<?php
/**
 * API: Quizzes
 * Manage and submit quizzes
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
$action = $_GET['action'] ?? 'load';

// ─── GET: Load Quiz Questions ────────────────────────────────────────────────
if ($method === 'GET' && $action === 'load') {
    // BUG FIX: Validate module_id is present and > 0
    if (!isset($_GET['module_id'])) {
        jsonResponse(false, 'module_id parameter is required.', [], 422);
    }

    $modId = (int)$_GET['module_id'];

    if ($modId < 1) {
        jsonResponse(false, 'Invalid module_id. Must be a positive integer.', [], 422);
    }

    // Verify module exists and is a quiz type
    $modCheck = db()->prepare("SELECT id, type FROM course_modules WHERE id = ?");
    $modCheck->execute([$modId]);
    $mod = $modCheck->fetch();

    if (!$mod) {
        jsonResponse(false, 'Module not found.', [], 404);
    }

    if ($mod['type'] !== 'quiz') {
        jsonResponse(false, 'This module is not a quiz.', [], 422);
    }

    $stmt = db()->prepare("SELECT id, question_text, points FROM quiz_questions WHERE module_id = ? ORDER BY id ASC");
    $stmt->execute([$modId]);
    $questions = $stmt->fetchAll();

    foreach ($questions as &$q) {
        $optSql = isAdmin()
            ? "SELECT id, option_text, is_correct FROM quiz_options WHERE question_id = ? ORDER BY id ASC"
            : "SELECT id, option_text FROM quiz_options WHERE question_id = ? ORDER BY id ASC";

        $optStmt = db()->prepare($optSql);
        $optStmt->execute([$q['id']]);
        $q['options'] = $optStmt->fetchAll();
    }

    jsonResponse(true, 'OK', ['questions' => $questions, 'module_id' => $modId]);
}

// ─── All POST routes require CSRF ─────────────────────────────────────────────
if ($method === 'POST') {
    requireCsrf();
}

// ─── Admin POST: Delete entire question ──────────────────────────────────────
if ($method === 'POST' && $action === 'delete_question') {
    if (!isAdmin()) jsonResponse(false, 'Access denied.', [], 403);
    $qId = (int)($_POST['question_id'] ?? 0);
    if ($qId < 1) jsonResponse(false, 'Invalid question ID.', [], 422);
    db()->prepare("DELETE FROM quiz_questions WHERE id = ?")->execute([$qId]);
    jsonResponse(true, 'Question deleted.');
}

// ─── Admin POST: Save Question ────────────────────────────────────────────────
if ($method === 'POST' && $action === 'save_question') {
    if (!isAdmin()) jsonResponse(false, 'Access denied.', [], 403);

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? [];

    $modId  = (int)($data['module_id'] ?? 0);
    $text   = sanitize($data['question_text'] ?? '');
    $points = (int)($data['points'] ?? 10);
    $opts   = $data['options'] ?? [];

    if ($modId < 1) jsonResponse(false, 'Invalid module_id.', [], 422);
    if (empty($text)) jsonResponse(false, 'Question text is required.', [], 422);
    if (count($opts) < 2) jsonResponse(false, 'At least 2 options are required.', [], 422);

    // Validate at least one correct answer
    $hasCorrect = array_filter($opts, fn($o) => !empty($o['is_correct']));
    if (empty($hasCorrect)) {
        jsonResponse(false, 'At least one option must be marked as correct.', [], 422);
    }

    try {
        db()->beginTransaction();

        $qStmt = db()->prepare("INSERT INTO quiz_questions (module_id, question_text, points) VALUES (?, ?, ?)");
        $qStmt->execute([$modId, $text, $points]);
        $qId = db()->lastInsertId();

        $optStmt = db()->prepare("INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
        foreach ($opts as $o) {
            $optText   = sanitize($o['text'] ?? '');
            $isCorrect = !empty($o['is_correct']) ? 1 : 0;
            if ($optText !== '') {
                $optStmt->execute([$qId, $optText, $isCorrect]);
            }
        }

        db()->commit();
        jsonResponse(true, 'Question added successfully!');
    } catch (Exception $e) {
        db()->rollBack();
        error_log('Quiz save error: ' . $e->getMessage());
        jsonResponse(false, 'Database error while saving question.', [], 500);
    }
}

// ─── User POST: Submit Quiz ──────────────────────────────────────────────────
if ($method === 'POST' && $action === 'submit') {
    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true) ?? [];

    $modId   = (int)($data['module_id'] ?? 0);
    $answers = $data['answers'] ?? [];
    $userId  = (int)$_SESSION['user_id'];

    if ($modId < 1) jsonResponse(false, 'Invalid module_id.', [], 422);

    $qStmt = db()->prepare("SELECT id, points FROM quiz_questions WHERE module_id = ?");
    $qStmt->execute([$modId]);
    $questions = $qStmt->fetchAll();

    if (empty($questions)) {
        jsonResponse(false, 'No questions found for this quiz.', [], 404);
    }

    $totalScore = 0;
    $maxScore   = 0;

    foreach ($questions as $q) {
        $qId = $q['id'];
        $maxScore += $q['points'];

        $chosenOpt = (int)($answers[$qId] ?? 0);
        if ($chosenOpt > 0) {
            $chkStmt = db()->prepare("SELECT is_correct FROM quiz_options WHERE id = ? AND question_id = ?");
            $chkStmt->execute([$chosenOpt, $qId]);
            $isCor = (int)$chkStmt->fetchColumn();
            if ($isCor === 1) {
                $totalScore += $q['points'];
            }
        }
    }

    $passed = $maxScore > 0 ? ($totalScore / $maxScore >= 0.5 ? 1 : 0) : 1;

    try {
        db()->beginTransaction();

        $attStmt = db()->prepare("INSERT INTO quiz_attempts (user_id, module_id, score, total_points, passed) VALUES (?, ?, ?, ?, ?)");
        $attStmt->execute([$userId, $modId, $totalScore, $maxScore, $passed]);

        // Mark module progress if not already done
        $chkProg = db()->prepare("SELECT id FROM module_progress WHERE user_id = ? AND module_id = ?");
        $chkProg->execute([$userId, $modId]);
        if (!$chkProg->fetch()) {
            db()->prepare("INSERT INTO module_progress (user_id, module_id) VALUES (?, ?)")->execute([$userId, $modId]);

            // Recalculate course progress
            $cStmt = db()->prepare("SELECT course_id FROM course_modules WHERE id = ?");
            $cStmt->execute([$modId]);
            $cId = (int)$cStmt->fetchColumn();

            if ($cId > 0) {
                $allStmt = db()->prepare("SELECT COUNT(*) FROM course_modules WHERE course_id = ?");
                $allStmt->execute([$cId]);
                $totMods = (int)$allStmt->fetchColumn();

                $compStmt = db()->prepare("
                    SELECT COUNT(*) FROM course_modules m
                    JOIN module_progress mp ON mp.module_id = m.id
                    WHERE m.course_id = ? AND mp.user_id = ?
                ");
                $compStmt->execute([$cId, $userId]);
                $compMods = (int)$compStmt->fetchColumn();

                $pct    = $totMods > 0 ? floor(($compMods / $totMods) * 100) : 0;
                $compAt = $pct >= 100 ? date('Y-m-d H:i:s') : null;

                db()->prepare("UPDATE enrollments SET progress = ?, completed_at = ? WHERE user_id = ? AND course_id = ?")
                    ->execute([$pct, $compAt, $userId, $cId]);

                // Award completion achievement
                if ($pct >= 100) {
                    $nameStmt = db()->prepare("SELECT title FROM courses WHERE id = ?");
                    $nameStmt->execute([$cId]);
                    $title = $nameStmt->fetchColumn();
                    db()->prepare("INSERT IGNORE INTO achievements (user_id, title, description) VALUES (?, ?, ?)")
                        ->execute([$userId, 'Completed: ' . $title, 'You finished all modules in this playlist!']);
                }
            }
        }

        db()->commit();
    } catch (Exception $e) {
        db()->rollBack();
        error_log('Quiz submit error: ' . $e->getMessage());
        jsonResponse(false, 'Failed to save quiz attempt. Please try again.', [], 500);
    }

    jsonResponse(true, 'Quiz submitted!', [
        'score'  => $totalScore,
        'max'    => $maxScore,
        'passed' => (bool)$passed,
        'pct'    => $maxScore > 0 ? round(($totalScore / $maxScore) * 100) : 100,
    ]);
}

jsonResponse(false, 'Invalid request.', [], 400);
