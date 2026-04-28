<?php
/**
 * User Dashboard
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

session_name(SESSION_NAME);
session_start();
requireLogin();

$csrfToken = generateCsrfToken();
$user      = currentUser();
$logoutUrl = BASE_URL . '/auth/logout.php?token=' . urlencode($csrfToken);

// Determine initial progress percentage via DB
$stmt = db()->prepare("
    SELECT IFNULL(AVG(progress), 0) as overall
    FROM enrollments WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$overallProgress = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
  <title>My Learning — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
  <style>
    .split-modal-body {
        display: flex;
        flex-direction: row;
        height: 70vh;
        max-height: 800px;
    }
    .modal-viewer {
        flex: 1;
        background: #000;
        display: flex;
        flex-direction: column;
        justify-content: center;
        position: relative;
    }
    .modal-sidebar {
        width: 300px;
        background: rgba(255,255,255,0.03);
        border-left: 1px solid rgba(255,255,255,0.1);
        overflow-y: auto;
        padding: 1rem;
    }
    @media (max-width: 768px) {
        .split-modal-body { flex-direction: column; height: 85vh; }
        .modal-sidebar { width: 100%; border-left: none; border-top: 1px solid rgba(255,255,255,0.1); }
        .modal-viewer { min-height: 300px; }
    }
    
    .playlist-item {
        padding: 1rem;
        background: rgba(255,255,255,0.02);
        border: 1px solid rgba(255,255,255,0.05);
        margin-bottom: .5rem;
        border-radius: .5rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .playlist-item:hover { background: rgba(255,255,255,0.06); }
    .playlist-item.active { border-color: #6c63ff; background: rgba(108,99,255,0.1); }
    .playlist-item.completed { opacity: 0.7; }
    .item-title { font-weight: 500; font-size: 0.95rem; margin-bottom: .25rem; display: flex; justify-content: space-between;}
    .item-type { font-size: 0.75rem; color: var(--color-text-muted); }
    
    .quiz-container { padding: 2rem; overflow-y: auto; height: 100%; background: var(--bg-dashboard); color: #fff;}
    .quiz-question { margin-bottom: 2rem; background: rgba(255,255,255,0.03); padding: 1.5rem; border-radius: .5rem; }
    .quiz-option { 
        display: block; padding: 1rem; margin-bottom: .5rem; 
        background: rgba(255,255,255,0.05); border-radius: .25rem; cursor: pointer;
    }
    .quiz-option:hover { background: rgba(255,255,255,0.1); }
    .quiz-option input { margin-right: .5rem; }
  </style>
</head>
<body>

<div id="page-loader" class="page-loader"><div class="spinner"></div></div>
<div class="bg-dots" aria-hidden="true"></div>

<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<div class="dashboard-layout">
  <!-- ── Sidebar ─────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar">
    <a href="<?= BASE_URL ?>/user/dashboard.php" class="sidebar-logo">
      <div class="sidebar-logo-icon">🏆</div>
      <span class="sidebar-logo-text"><?= APP_NAME ?></span>
    </a>

    <nav class="sidebar-nav">
      <span class="nav-section-label">Menu</span>
      <a href="<?= BASE_URL ?>/user/dashboard.php" class="nav-item active">
        <span class="nav-item-icon">📚</span> My Playlists
      </a>
      <?php if (isAdmin()): ?>
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-item" style="color:var(--color-purple)">
        <span class="nav-item-icon">⚙️</span> Admin Panel
      </a>
      <?php endif; ?>
    </nav>

    <div class="sidebar-user">
      <div class="sidebar-user-avatar">
        <?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1)), ENT_QUOTES) ?>
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= htmlspecialchars($user['name'], ENT_QUOTES) ?></div>
        <div class="sidebar-user-role">Student</div>
      </div>
      <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES) ?>" class="sidebar-logout" title="Logout">⬛</a>
    </div>
  </aside>

  <!-- ── Main Content ────────────────────────────────── -->
  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="menu-toggle" id="menu-toggle">☰</button>
        <div>
          <div class="page-title">My Learning</div>
          <div class="page-subtitle">Keep up the momentum!</div>
        </div>
      </div>
      <div class="topbar-right">
        <!-- Optional global profile quicklinks -->
      </div>
    </header>

    <main class="dashboard-body">

      <!-- Overall Progress -->
      <div class="glass-card" style="padding:1.5rem; margin-bottom:2rem; display:flex; align-items:center; gap:1.5rem">
        <div class="stat-icon stat-icon-cyan" style="font-size:2rem; width:64px; height:64px">🚀</div>
        <div style="flex:1">
          <h2 style="margin:0 0 .5rem; font-size:1.25rem">Overall Progress</h2>
          <div class="progress-bar-container">
            <div class="progress-bar-fill" style="width: <?= $overallProgress ?>%"></div>
          </div>
        </div>
        <div style="font-size:1.5rem; font-weight:700; color:var(--color-cyan)"><?= $overallProgress ?>%</div>
      </div>

      <!-- Achievements -->
      <div class="glass-card p-6" style="margin-bottom:2rem">
        <h2 class="section-title">My Achievements</h2>
        <div id="achievements-container" style="display:flex; gap:1rem; flex-wrap:wrap">
          <div class="text-muted">Loading achievements...</div>
        </div>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <div class="topbar-search">
          <input type="search" id="course-search" class="form-input" placeholder="Search playlists...">
        </div>
        <div>
           <!-- Filters empty for now -->
        </div>
      </div>

      <!-- Course Grid -->
      <div class="course-grid" id="course-grid">
        <div class="spinner" style="grid-column:1/-1; margin:auto"></div>
      </div>
      
      <div id="courses-pagination"></div>

    </main>
  </div>
</div>

<!-- ══ Playlist Viewer Modal ════════════════════════════ -->
<div class="modal-overlay" id="course-viewer-modal">
  <div class="modal" style="max-width:1200px; padding:0; overflow:hidden">
    <div class="modal-header" style="background:rgba(255,255,255,0.03); border-bottom:1px solid rgba(255,255,255,0.1)">
      <h2 class="modal-title" id="viewer-title">Loading...</h2>
      <button class="modal-close" onclick="Modal.close('course-viewer-modal')">✕</button>
    </div>
    
    <div class="split-modal-body">
      <div class="modal-viewer" id="viewer-content">
        <!-- Iframe or Quiz UI goes here -->
      </div>
      <div class="modal-sidebar" id="playlist-sidebar">
        <!-- Module list goes here -->
      </div>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/user.js"></script>
</body>
</html>
