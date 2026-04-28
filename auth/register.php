<?php
/**
 * Register Page
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

session_name(SESSION_NAME);
session_start();

// Already logged in — redirect
if (isLoggedIn()) {
    redirectAbsolute(BASE_URL . (isAdmin() ? '/admin/dashboard.php' : '/user/dashboard.php'));
}

$csrfToken = generateCsrfToken();
$flash     = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Create your AchieveHub account and start learning today.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
  <title>Register — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body>

<div id="page-loader" class="page-loader"><div class="spinner"></div></div>

<div class="bg-dots" aria-hidden="true"></div>
<div class="auth-bg-orb auth-bg-orb-1" aria-hidden="true"></div>
<div class="auth-bg-orb auth-bg-orb-2" aria-hidden="true"></div>
<div class="auth-bg-orb auth-bg-orb-3" aria-hidden="true"></div>

<?php if ($flash): ?>
<div id="flash-data" data-type="<?= htmlspecialchars($flash['type'], ENT_QUOTES) ?>"
     data-message="<?= htmlspecialchars($flash['message'], ENT_QUOTES) ?>" hidden></div>
<?php endif; ?>

<main class="auth-page">
  <div class="auth-card" style="max-width:480px">

    <a href="<?= BASE_URL ?>" class="auth-logo" aria-label="<?= APP_NAME ?> Home">
      <div class="auth-logo-icon" aria-hidden="true">🏆</div>
      <span class="auth-logo-text"><?= APP_NAME ?></span>
    </a>

    <div class="auth-header">
      <h1 class="auth-title">Create your account</h1>
      <p class="auth-subtitle">Join thousands of learners on AchieveHub</p>
    </div>

    <form id="register-form" class="auth-form" novalidate autocomplete="on">

      <!-- Full Name -->
      <div class="input-group form-group">
        <label class="form-label" for="name">Full name</label>
        <div class="input-icon-wrapper">
          <span class="input-icon" aria-hidden="true">👤</span>
          <input type="text" id="name" name="name" class="form-input"
                 placeholder="John Doe" autocomplete="name" required>
        </div>
      </div>

      <!-- Username -->
      <div class="input-group form-group">
        <label class="form-label" for="username">Username</label>
        <div class="input-icon-wrapper">
          <span class="input-icon" aria-hidden="true">@</span>
          <input type="text" id="username" name="username" class="form-input"
                 placeholder="johndoe_99" autocomplete="username" required
                 pattern="[a-z0-9_]{3,30}">
        </div>
      </div>

      <!-- Email -->
      <div class="input-group form-group">
        <label class="form-label" for="email">Email address</label>
        <div class="input-icon-wrapper">
          <span class="input-icon" aria-hidden="true">✉️</span>
          <input type="email" id="email" name="email" class="form-input"
                 placeholder="you@example.com" autocomplete="email" required>
        </div>
      </div>

      <!-- Password -->
      <div class="input-group form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-icon-wrapper" style="position:relative">
          <span class="input-icon" aria-hidden="true">🔒</span>
          <input type="password" id="password" name="password" class="form-input"
                 placeholder="Min. 8 characters" autocomplete="new-password" required>
          <button type="button" class="password-toggle" aria-label="Toggle password visibility">👁️</button>
        </div>
        <!-- Password Strength -->
        <div class="password-strength" id="password-strength">
          <div class="strength-bars">
            <div class="strength-bar" id="bar-1"></div>
            <div class="strength-bar" id="bar-2"></div>
            <div class="strength-bar" id="bar-3"></div>
            <div class="strength-bar" id="bar-4"></div>
          </div>
          <span class="strength-label"></span>
        </div>
      </div>

      <!-- Confirm Password -->
      <div class="input-group form-group">
        <label class="form-label" for="confirm_password">Confirm password</label>
        <div class="input-icon-wrapper" style="position:relative">
          <span class="input-icon" aria-hidden="true">🔑</span>
          <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                 placeholder="Repeat your password" autocomplete="new-password" required>
          <button type="button" class="password-toggle" aria-label="Toggle password visibility">👁️</button>
        </div>
      </div>

      <button type="submit" id="register-btn" class="btn-auth">
        <span class="spinner-sm"></span>
        <span class="btn-label">Create Account</span>
      </button>

    </form>

    <div class="auth-footer">
      Already have an account?
      <a href="<?= BASE_URL ?>/auth/login.php">Sign in</a>
    </div>

  </div>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/auth.js"></script>
</body>
</html>
