<?php
/**
 * CSRF Protection
 * Generates and validates CSRF tokens stored in the session.
 */

/**
 * Generate a CSRF token and store in session.
 * Returns the token string to embed in forms.
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the submitted CSRF token against the session token.
 * Uses hash_equals for timing-safe comparison.
 */
function validateCsrfToken(string $submittedToken): bool
{
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/**
 * Render a hidden CSRF input field for use in forms.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES) . '">';
}

/**
 * Validate CSRF from POST or JSON body — or kill with 403.
 * Call at the top of every POST-handling API/page.
 */
function requireCsrf(): void
{
    $token = $_POST['csrf_token']
          ?? $_SERVER['HTTP_X_CSRF_TOKEN']
          ?? $_SERVER['HTTP_X_XSRF_TOKEN']
          ?? '';

    if (empty($token)) {
        $raw  = file_get_contents('php://input');
        if (!empty($raw)) {
            $json = json_decode($raw, true);
            $token = $json['csrf_token'] ?? '';
        }
    }

    if (!validateCsrfToken($token)) {
        error_log("CSRF FAILED: Token received: [" . $token . "], Session token: [" . ($_SESSION['csrf_token'] ?? 'EMPTY') . "]");
        http_response_code(403);
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest' || 
            str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Invalid or missing CSRF token.']);
            exit;
        } else {
            die('403 Forbidden — CSRF token mismatch.');
        }
    }
}
