<?php
/**
 * Admin Dashboard
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

session_name(SESSION_NAME);
session_start();
requireAdmin();

$csrfToken  = generateCsrfToken();
$user       = currentUser();
$logoutUrl  = BASE_URL . '/auth/logout.php?token=' . urlencode($csrfToken);
$flash      = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Admin Dashboard — Manage courses and users on AchieveHub.">
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
  <title>Admin Dashboard — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
</head>
<body>

<div id="page-loader" class="page-loader"><div class="spinner"></div></div>
<div class="bg-dots" aria-hidden="true"></div>

<?php if ($flash): ?>
<div id="flash-data" data-type="<?= htmlspecialchars($flash['type'], ENT_QUOTES) ?>"
     data-message="<?= htmlspecialchars($flash['message'], ENT_QUOTES) ?>" hidden></div>
<?php endif; ?>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<div class="dashboard-layout">

  <!-- ── Sidebar ─────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar" aria-label="Admin navigation">
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-logo">
      <div class="sidebar-logo-icon" aria-hidden="true">🏆</div>
      <span class="sidebar-logo-text"><?= APP_NAME ?></span>
    </a>

    <nav class="sidebar-nav">
      <span class="nav-section-label">Overview</span>

      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-item active" aria-current="page">
        <span class="nav-item-icon" aria-hidden="true">📊</span>
        Dashboard
      </a>

      <span class="nav-section-label">Content</span>

      <a href="javascript:void(0)" class="nav-item" onclick="Modal.open('add-course-modal')">
        <span class="nav-item-icon" aria-hidden="true">➕</span>
        Add Course Playlist
      </a>

      <a href="<?= BASE_URL ?>/user/dashboard.php" class="nav-item">
        <span class="nav-item-icon" aria-hidden="true">📚</span>
        View as User
      </a>
    </nav>

    <div class="sidebar-user">
      <div class="sidebar-user-avatar" aria-hidden="true">
        <?= htmlspecialchars(strtoupper(substr($user['name'], 0, 1)), ENT_QUOTES) ?>
      </div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name"><?= htmlspecialchars($user['name'], ENT_QUOTES) ?></div>
        <div class="sidebar-user-role">Administrator</div>
      </div>
      <a href="<?= htmlspecialchars($logoutUrl, ENT_QUOTES) ?>" class="sidebar-logout" title="Logout" aria-label="Logout">⬛</a>
    </div>
  </aside>

  <!-- ── Main Content ────────────────────────────────── -->
  <div class="main-content">

    <!-- Topbar -->
    <header class="topbar">
      <div class="topbar-left">
        <button class="menu-toggle" id="menu-toggle" aria-label="Toggle sidebar" aria-expanded="false">☰</button>
        <div>
          <div class="page-title">Dashboard</div>
          <div class="page-subtitle">Welcome back, <?= htmlspecialchars($user['name'], ENT_QUOTES) ?></div>
        </div>
      </div>

      <div class="topbar-right">
        <div class="topbar-search">
          <span class="topbar-search-icon" aria-hidden="true">🔍</span>
          <input type="search" id="course-search" class="form-input" placeholder="Search playlists…" aria-label="Search courses">
        </div>
        <button class="btn btn-primary btn-sm" onclick="Modal.open('add-course-modal')">
          ➕ New Playlist
        </button>
      </div>
    </header>

    <!-- Body -->
    <main class="dashboard-body">

      <!-- Stat Cards -->
      <div class="stat-grid">
        <div class="glass-card stat-card stat-card-purple">
          <div class="stat-icon stat-icon-purple" aria-hidden="true">👥</div>
          <div class="stat-value" id="stat-users">—</div>
          <div class="stat-label">Total Users</div>
        </div>
        <div class="glass-card stat-card stat-card-cyan">
          <div class="stat-icon stat-icon-cyan" aria-hidden="true">📚</div>
          <div class="stat-value" id="stat-courses">—</div>
          <div class="stat-label">Total Playlists</div>
        </div>
        <div class="glass-card stat-card stat-card-pink">
          <div class="stat-icon stat-icon-pink" aria-hidden="true">🎓</div>
          <div class="stat-value" id="stat-enrollments">—</div>
          <div class="stat-label">Enrollments</div>
        </div>
        <div class="glass-card stat-card stat-card-success">
          <div class="stat-icon stat-icon-success" aria-hidden="true">🏆</div>
          <div class="stat-value" id="stat-completed">—</div>
          <div class="stat-label">Completions</div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="dashboard-grid" style="margin-bottom:2rem">
        <!-- Enrollment Trend -->
        <div class="glass-card p-6">
          <div class="section-header">
            <h2 class="section-title">Enrollment Trend</h2>
            <span class="badge badge-purple">Last 7 Days</span>
          </div>
          <div class="chart-container">
            <canvas id="enrollment-chart" aria-label="Enrollment trend chart"></canvas>
          </div>
        </div>

        <div class="glass-card p-6">
          <!-- Removed course type doughnut as types are at module level now -->
          <div class="section-header">
            <h2 class="section-title">Top Playlists</h2>
          </div>
          <div class="table-wrapper">
            <table aria-label="Top courses by enrollment">
              <thead>
                <tr>
                  <th>Course</th>
                  <th style="text-align:right">Enrollments</th>
                </tr>
              </thead>
              <tbody id="top-courses-body">
                <tr><td colspan="2" class="text-center"><div class="animate-pulse text-muted">Loading…</div></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Bottom Row -->
      <div class="dashboard-grid">
        <!-- Recent Users -->
        <div class="glass-card p-6" style="grid-column: 1 / -1">
          <div class="section-header">
            <h2 class="section-title">Recent Users</h2>
          </div>
          <div id="recent-users">
            <div class="animate-pulse text-muted text-center">Loading…</div>
          </div>
        </div>
      </div>

      <!-- Courses Table -->
      <div class="glass-card" style="margin-top:2rem">
        <div class="section-header" style="padding:1.5rem 1.5rem 0">
          <h2 class="section-title">All Course Playlists</h2>
          <button class="btn btn-primary btn-sm" onclick="Modal.open('add-course-modal')">
            ➕ Add Playlist
          </button>
        </div>
        <div class="table-wrapper" style="padding:1.5rem">
          <table aria-label="All courses">
            <thead>
              <tr>
                <th>Title</th>
                <th>Modules</th>
                <th>Author</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="courses-table-body">
              <tr><td colspan="5" class="text-center" style="padding:2rem">
                <div class="spinner" style="margin:auto;width:32px;height:32px"></div>
              </td></tr>
            </tbody>
          </table>
          <div id="courses-pagination"></div>
        </div>
      </div>

    </main>
  </div><!-- /main-content -->
</div><!-- /dashboard-layout -->

<!-- ══ Add Course Modal ═════════════════════════════════ -->
<div class="modal-overlay" id="add-course-modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title" id="modal-title">➕ Create New Playlist</h2>
      <button class="modal-close" onclick="Modal.close('add-course-modal')" aria-label="Close modal">✕</button>
    </div>

    <div class="modal-body">
      <form id="add-course-form" enctype="multipart/form-data">
        <!-- Title -->
        <div class="form-group" style="margin-bottom:1.25rem">
          <label class="form-label" for="course-title">Playlist Title <span style="color:var(--color-error)">*</span></label>
          <input type="text" id="course-title" name="title" class="form-input"
                 placeholder="e.g. Masterclass: Advanced AI" required maxlength="200">
        </div>

        <!-- Thumbnail Image -->
        <div class="form-group" style="margin-bottom:1.25rem">
          <label class="form-label">Playlist Thumbnail <span style="color:var(--color-text-muted);font-weight:400">(Optional — JPG, PNG, WEBP · max 5MB)</span></label>
          <input type="file" id="add-course-thumbnail" name="thumbnail" class="form-input" accept="image/jpeg,image/png,image/webp">
          <img id="add-course-thumb-preview" src="" alt="Preview"
               style="display:none;margin-top:.75rem;max-height:140px;border-radius:.5rem;object-fit:cover;width:100%;border:1px solid rgba(255,255,255,0.1)">
        </div>

        <!-- Description -->
        <div class="form-group" style="margin-bottom:1.25rem">
          <label class="form-label" for="course-desc">Description</label>
          <textarea id="course-desc" name="description" class="form-textarea"
                    placeholder="Brief overview of the entire curriculum" rows="3"></textarea>
        </div>
      </form>
    </div>

    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="Modal.close('add-course-modal')">Cancel</button>
      <button class="btn btn-primary" id="add-course-btn"
              onclick="document.getElementById('add-course-form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}))">
        ➕ Create Container
      </button>
    </div>
  </div>
</div>

<!-- ══ Thumbnail Upload Modal ════════════════════════════ -->
<div class="modal-overlay" id="thumbnail-modal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <h2 class="modal-title">🖼 Update Thumbnail</h2>
      <button class="modal-close" onclick="Modal.close('thumbnail-modal')">✕</button>
    </div>
    <div class="modal-body">
      <p style="color:var(--color-text-muted);margin-top:0">Course: <strong id="thumb-modal-course-name" style="color:var(--color-text-primary)"></strong></p>
      <div class="form-group" style="margin-bottom:1rem">
        <label class="form-label">Select Image <span style="color:var(--color-text-muted);font-weight:400">(JPG, PNG, WEBP · max 5MB)</span></label>
        <input type="file" id="thumb-file-input" class="form-input" accept="image/jpeg,image/png,image/webp">
        <img id="thumb-preview" src="" alt="Preview"
             style="display:none;margin-top:.75rem;max-height:180px;width:100%;object-fit:cover;border-radius:.5rem;border:1px solid rgba(255,255,255,0.1)">
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="Modal.close('thumbnail-modal')">Cancel</button>
      <button class="btn btn-primary" id="thumb-upload-btn" onclick="uploadThumbnail()">Upload Thumbnail</button>
    </div>
  </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
</body>
</html>
