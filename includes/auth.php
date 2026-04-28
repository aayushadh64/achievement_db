<?php
/**
 * Authentication Logic
 * Login, register, and session management using PDO prepared statements.
 */

/**
 * Attempt to log in a user.
 * Returns user array on success, or an error string on failure.
 */
function loginUser(string $email, string $password): array|string
{
    $email = strtolower(trim($email));

    if (empty($email) || empty($password)) {
        return 'Email and password are required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }

    $stmt = db()->prepare(
        "SELECT id, name, email, password, role, is_active FROM users WHERE email = ? LIMIT 1"
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return 'Invalid email or password.';
    }

    if (!$user['is_active']) {
        return 'Your account has been deactivated. Please contact support.';
    }

    // Update last login timestamp
    db()->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

    // Regenerate session ID to prevent fixation attacks
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['role']      = $user['role'];

    return $user;
}

/**
 * Register a new user.
 * Returns true on success, or an error string on failure.
 */
function registerUser(string $name, string $username, string $email, string $password, string $confirmPassword): bool|string
{
    $name     = trim($name);
    $username = strtolower(trim($username));
    $email    = strtolower(trim($email));

    // Validation
    if (empty($name) || empty($username) || empty($email) || empty($password)) {
        return 'All fields are required.';
    }

    if (strlen($name) < 2 || strlen($name) > 100) {
        return 'Name must be between 2 and 100 characters.';
    }

    if (!preg_match('/^[a-z0-9_]{3,30}$/', $username)) {
        return 'Username may only contain lowercase letters, numbers, and underscores (3-30 chars).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }

    if ($password !== $confirmPassword) {
        return 'Passwords do not match.';
    }

    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one uppercase letter and one number.';
    }

    // Check for duplicate email or username
    $stmt = db()->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) {
        return 'An account with that email or username already exists.';
    }

    // Hash password with bcrypt (cost 12)
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    $stmt = db()->prepare(
        "INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, 'user')"
    );
    $stmt->execute([$name, $username, $email, $hashedPassword]);

    return true;
}

/**
 * Destroy the current session and log the user out.
 */
function logoutUser(): void
{
    // Unset all session variables
    $_SESSION = [];

    // Delete the session cookie
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}
