<?php
/**
 * Login Page
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
  <meta name="description" content="Sign in to AchieveHub — your personal learning achievement platform.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/auth.css">
</head>
<body>

<!-- Page Loader -->
<div id="page-loader" class="page-loader">
  <div class="spinner"></div>
</div>

<!-- Background -->
<div class="bg-dots" aria-hidden="true"></div>
<div class="auth-bg-orb auth-bg-orb-1" aria-hidden="true"></div>
<div class="auth-bg-orb auth-bg-orb-2" aria-hidden="true"></div>
<div class="auth-bg-orb auth-bg-orb-3" aria-hidden="true"></div>

<!-- Flash from session -->
<?php if ($flash): ?>
<div id="flash-data" data-type="<?= htmlspecialchars($flash['type'], ENT_QUOTES) ?>"
     data-message="<?= htmlspecialchars($flash['message'], ENT_QUOTES) ?>" hidden></div>
<?php endif; ?>

<main class="auth-page">
  <div class="auth-card">

    <!-- Logo -->
    <a href="<?= BASE_URL ?>" class="auth-logo" aria-label="<?= APP_NAME ?> Home">
      <div class="auth-logo-icon" aria-hidden="true">🏆</div>
      <span class="auth-logo-text"><?= APP_NAME ?></span>
    </a>

    <!-- Header -->
    <div class="auth-header">
      <h1 class="auth-title">Welcome back</h1>
      <p class="auth-subtitle">Sign in to continue your learning journey</p>
    </div>

    <!-- Login Form -->
    <form id="login-form" class="auth-form" novalidate autocomplete="on">

      <!-- Email -->
      <div class="input-group form-group">
        <label class="form-label" for="email">Email address</label>
        <div class="input-icon-wrapper">
          <span class="input-icon" aria-hidden="true">✉️</span>
          <input
            type="email"
            id="email"
            name="email"
            class="form-input"
            placeholder="you@example.com"
            autocomplete="email"
            required
          >
        </div>
      </div>

      <!-- Password -->
      <div class="input-group form-group">
        <label class="form-label" for="password">Password</label>
        <div class="input-icon-wrapper" style="position:relative">
          <span class="input-icon" aria-hidden="true">🔒</span>
          <input
            type="password"
            id="password"
            name="password"
            class="form-input"
            placeholder="Your password"
            autocomplete="current-password"
            required
          >
          <button type="button" class="password-toggle" aria-label="Toggle password visibility">👁️</button>
        </div>
      </div>

      <!-- Submit -->
      <button type="submit" id="login-btn" class="btn-auth">
        <span class="spinner-sm"></span>
        <span class="btn-label">Sign In</span>
      </button>

    </form>

    <!-- Footer -->
    <div class="auth-footer">
      Don't have an account?
      <a href="<?= BASE_URL ?>/auth/register.php">Create one free</a>
    </div>

    <!-- Demo credentials hint -->
    <div style="margin-top:1.5rem;padding:1rem;background:rgba(108,99,255,0.08);border:1px solid rgba(108,99,255,0.2);border-radius:.625rem;font-size:.75rem;color:var(--color-text-muted);text-align:center">
      <strong style="color:var(--color-purple-light)">Demo Admin:</strong>
      admin@achievehub.com / <code style="color:var(--color-cyan)">Admin@123</code>
      <br>(Password is the default — change it after first login)
    </div>

  </div>
</main>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/auth.js"></script>
</body>
</html>
