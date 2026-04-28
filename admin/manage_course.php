<?php
/**
 * Admin: Manage Course Playlist
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';

session_name(SESSION_NAME);
session_start();
requireAdmin();

$courseId = (int)($_GET['id'] ?? 0);
if ($courseId < 1) {
    redirectAbsolute(BASE_URL . '/admin/dashboard.php');
}

$stmt = db()->prepare("SELECT * FROM courses WHERE id = ?");
$stmt->execute([$courseId]);
$course = $stmt->fetch();

if (!$course) {
    redirectAbsolute(BASE_URL . '/admin/dashboard.php');
}

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
  <meta name="base-url" content="<?= BASE_URL ?>">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>">
  <title>Manage Playlist: <?= htmlspecialchars($course['title']) ?> — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard.css">
  <!-- Small inline style for builder -->
  <style>
    .module-card {
        background: rgba(255,255,255,0.03);
        border: 1px solid rgba(255,255,255,0.1);
        padding: 1rem;
        border-radius: .5rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .module-card:hover {
        background: rgba(255,255,255,0.06);
    }
    .module-info h3 { margin: 0 0 .25rem 0; font-size: 1.1rem; }
    .module-info p { margin: 0; color: var(--color-text-muted); font-size: .85rem; }
    .type-badge {
        display: inline-block; padding: .1rem .4rem; border-radius: .25rem; font-size: .75rem; font-weight: bold; margin-right: .5rem;
    }
    .badge-youtube { background: rgba(255,0,0,0.2); color: #ff4444; }
    .badge-ebook { background: rgba(0,255,255,0.2); color: #00ffff; }
    .badge-quiz { background: rgba(255,165,0,0.2); color: #ffa500; }
  </style>
</head>
<body>

<div id="page-loader" class="page-loader"><div class="spinner"></div></div>
<div class="bg-dots" aria-hidden="true"></div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

<div class="dashboard-layout">
  <!-- ── Sidebar ─────────────────────────────────────── -->
  <aside class="sidebar" id="sidebar">
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="sidebar-logo">
      <div class="sidebar-logo-icon">🏆</div>
      <span class="sidebar-logo-text"><?= APP_NAME ?></span>
    </a>
    <nav class="sidebar-nav">
      <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-item">
        <span class="nav-item-icon">📊</span> Dashboard
      </a>
      <a href="javascript:void(0)" class="nav-item active">
        <span class="nav-item-icon">📝</span> Playlist Builder
      </a>
    </nav>
  </aside>

  <!-- ── Main Content ────────────────────────────────── -->
  <div class="main-content">
    <header class="topbar">
      <div class="topbar-left">
        <button class="menu-toggle" id="menu-toggle">☰</button>
        <div>
          <div class="page-title">Manage Playlist</div>
          <div class="page-subtitle"><?= htmlspecialchars($course['title']) ?></div>
        </div>
      </div>
      <div class="topbar-right">
        <button class="btn btn-secondary btn-sm" onclick="window.location.href='dashboard.php'">← Back</button>
      </div>
    </header>

    <main class="dashboard-body">
      <div class="dashboard-grid">
        <!-- Left: Module List -->
        <div class="glass-card p-6" style="grid-column: 1 / 3;">
          <div class="section-header" style="justify-content:space-between">
            <h2 class="section-title">Playlist Modules</h2>
            <button class="btn btn-primary" onclick="Modal.open('add-module-modal')">➕ Add Module</button>
          </div>
          
          <div id="modules-list">
             <div class="text-center text-muted" style="padding:2rem">Loading modules...</div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<!-- ══ Add Module Modal ═════════════════════════════════ -->
<div class="modal-overlay" id="add-module-modal">
  <div class="modal">
    <div class="modal-header">
      <h2 class="modal-title">➕ Add Module</h2>
      <button class="modal-close" onclick="Modal.close('add-module-modal')">✕</button>
    </div>
    <div class="modal-body">
      <form id="add-module-form">
        <input type="hidden" name="course_id" value="<?= $courseId ?>">
        
        <div class="form-group" style="margin-bottom:1.25rem">
          <label class="form-label">Title *</label>
          <input type="text" name="title" class="form-input" required maxlength="200">
        </div>

        <div class="form-group" style="margin-bottom:1.25rem">
          <label class="form-label">Type *</label>
          <select id="module-type-sel" name="type" class="form-select" required onchange="toggleModuleFields()">
            <option value="youtube">▶ YouTube Video</option>
            <option value="ebook">📄 Ebook (PDF/EPUB)</option>
            <option value="quiz">📝 Quiz Assessment</option>
          </select>
        </div>

        <div id="youtube-fields" class="form-group" style="margin-bottom:1.25rem">
          <label class="form-label">YouTube URL</label>
          <input type="url" name="youtube_url" class="form-input">
        </div>

        <div id="ebook-fields" class="form-group" style="margin-bottom:1.25rem;display:none">
          <label class="form-label">Upload Ebook</label>
          <input type="file" name="ebook_file" class="form-input" accept=".pdf,.epub">
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="Modal.close('add-module-modal')">Cancel</button>
      <button class="btn btn-primary" id="save-module-btn">Save Module</button>
    </div>
  </div>
</div>

<!-- ══ Manage Quiz Modal ═════════════════════════════════ -->
<div class="modal-overlay" id="manage-quiz-modal">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <h2 class="modal-title">📝 Edit Quiz</h2>
      <button class="modal-close" onclick="Modal.close('manage-quiz-modal')">✕</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="manage_quiz_mod_id">
      
      <div id="quiz-questions-list" style="margin-bottom:2rem;"></div>

      <div style="border-top:1px solid rgba(255,255,255,0.1); padding-top:1rem;">
          <h4>Add New Question</h4>
          <textarea id="new_q_text" class="form-textarea" placeholder="Question Text" rows="2" style="margin-bottom:.5rem"></textarea>
          
          <div id="new_q_options">
             <!-- JS will append option inputs here -->
             <div style="display:flex;gap:.5rem;margin-bottom:.5rem">
                <input type="radio" name="new_q_correct" value="0" checked>
                <input type="text" class="form-input q-opt-input" placeholder="Option A">
             </div>
             <div style="display:flex;gap:.5rem;margin-bottom:.5rem">
                <input type="radio" name="new_q_correct" value="1">
                <input type="text" class="form-input q-opt-input" placeholder="Option B">
             </div>
             <div style="display:flex;gap:.5rem;margin-bottom:.5rem">
                <input type="radio" name="new_q_correct" value="2">
                <input type="text" class="form-input q-opt-input" placeholder="Option C">
             </div>
             <div style="display:flex;gap:.5rem;margin-bottom:.5rem">
                <input type="radio" name="new_q_correct" value="3">
                <input type="text" class="form-input q-opt-input" placeholder="Option D">
             </div>
          </div>
          
          <button class="btn btn-primary btn-sm" onclick="saveQuizQuestion()">Save Question</button>
      </div>

    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script>
const courseId = <?= $courseId ?>;
const baseUrl = document.querySelector('meta[name="base-url"]').content;

// Manage Fields
function toggleModuleFields() {
    const type = document.getElementById('module-type-sel').value;
    document.getElementById('youtube-fields').style.display = (type === 'youtube') ? 'block' : 'none';
    document.getElementById('ebook-fields').style.display = (type === 'ebook') ? 'block' : 'none';
}

// Load Modules
async function loadModules() {
    const container = document.getElementById('modules-list');
    container.innerHTML = '<div class="spinner" style="margin:2rem auto"></div>';
    
    try {
        const res = await apiFetch(`/api/modules.php?course_id=${courseId}`);
        if (!res.modules || res.modules.length === 0) {
            container.innerHTML = '<div class="text-muted">No modules added yet.</div>';
            return;
        }

        container.innerHTML = res.modules.map(mod => `
            <div class="module-card">
              <div class="module-info">
                  <h3>
                    <span class="type-badge badge-${mod.type}">${mod.type.toUpperCase()}</span>
                    ${escapeHtml(mod.title)}
                  </h3>
              </div>
              <div class="module-actions" style="display:flex;gap:.5rem">
                  ${mod.type === 'quiz' ? `<button class="btn btn-sm btn-secondary" onclick="openQuizManager(${mod.id})">Edit Quiz</button>` : ''}
                  <button class="btn btn-sm" style="background:#ff4444" onclick="deleteModule(${mod.id})">Del</button>
              </div>
            </div>
        `).join('');
    } catch(err) {
        Toast.error('Failed to load modules. Please refresh.');
    }
}

// Delete Module
async function deleteModule(id) {
    if (!confirm('Are you sure you want to delete this module?')) return;
    try {
        const formData = new FormData();
        formData.append('module_id', id);
        const res = await apiFetch('/api/modules.php?action=delete', { method: 'POST', body: formData });
        if (res.success) {
            Toast.success('Module deleted.');
            loadModules();
        } else {
            Toast.error(res.message || 'Delete failed.');
        }
    } catch(err) {
        Toast.error('Network error. Please try again.');
    }
}

// Add Module
document.getElementById('save-module-btn').addEventListener('click', async () => {
    const form = document.getElementById('add-module-form');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const formData = new FormData(form);
    const btn = document.getElementById('save-module-btn');
    setButtonLoading(btn, true);
    try {
        const res = await apiFetch('/api/modules.php?action=add', { method: 'POST', body: formData });
        if (res.success) {
            Modal.close('add-module-modal');
            form.reset();
            toggleModuleFields();
            Toast.success('Module created!');
            loadModules();
        } else {
            Toast.error(res.message || 'Failed to create module.');
        }
    } catch(err) {
        Toast.error('Network error. Please try again.');
    }
    setButtonLoading(btn, false);
});

// Quiz Loader
async function openQuizManager(modId) {
    document.getElementById('manage_quiz_mod_id').value = modId;
    Modal.open('manage-quiz-modal');
    loadQuizQuestions(modId);
}

async function loadQuizQuestions(modId) {
    const container = document.getElementById('quiz-questions-list');
    container.innerHTML = '<div class="spinner"></div>';
    try {
        const res = await apiFetch(`/api/quizzes.php?action=load&module_id=${modId}`);
        if(res.questions.length === 0) {
            container.innerHTML = '<p class="text-muted">No questions added yet.</p>';
            return;
        }
        
        container.innerHTML = res.questions.map((q, i) => `
            <div style="background:rgba(0,0,0,0.2);padding:1rem;border-radius:.5rem;margin-bottom:1rem">
                <div style="display:flex;justify-content:space-between">
                    <strong>Q${i+1}: ${escapeHtml(q.question_text)}</strong>
                    <button class="text-error" style="background:none;border:none;cursor:pointer" onclick="deleteQuestion(${q.id})">✕</button>
                </div>
                <ul style="margin: .5rem 0 0 1.5rem">
                    ${q.options.map(o => `
                        <li style="color:${o.is_correct ? '#00E676' : 'inherit'}">
                            ${escapeHtml(o.option_text)} ${o.is_correct ? '✓' : ''}
                        </li>
                    `).join('')}
                </ul>
            </div>
        `).join('');
    } catch(err) { container.innerHTML = 'Failed to load questions.'; }
}

async function deleteQuestion(qId) {
    if(!confirm('Delete this question?')) return;
    try {
        const formData = new FormData();
        formData.append('question_id', qId);
        const res = await apiFetch('/api/quizzes.php?action=delete_question', { method: 'POST', body: formData });
        if (res.success) {
            loadQuizQuestions(document.getElementById('manage_quiz_mod_id').value);
        } else {
            Toast.error(res.message || 'Delete failed.');
        }
    } catch(err) { Toast.error('Network error. Please try again.'); }
}

async function saveQuizQuestion() {
    const modId = document.getElementById('manage_quiz_mod_id').value;
    const text  = document.getElementById('new_q_text').value.trim();

    const optInputs    = document.querySelectorAll('.q-opt-input');
    const checkedIndex = parseInt(document.querySelector('input[name="new_q_correct"]:checked').value);

    const options = [];
    optInputs.forEach((inp, idx) => {
        if (inp.value.trim()) {
            options.push({ text: inp.value.trim(), is_correct: (idx === checkedIndex) });
        }
    });

    if (!text) { Toast.warning('Question text is required.'); return; }
    if (options.length < 2) { Toast.warning('Please provide at least 2 options.'); return; }
    if (!options.some(o => o.is_correct)) { Toast.warning('Please mark at least one option as correct.'); return; }

    try {
        const res = await apiFetch('/api/quizzes.php?action=save_question', {
            method:  'POST',
            body:    { module_id: parseInt(modId), question_text: text, points: 10, options },
        });

        if (res.success) {
            document.getElementById('new_q_text').value = '';
            optInputs.forEach(i => i.value = '');
            document.querySelector('input[name="new_q_correct"][value="0"]').checked = true;
            Toast.success('Question saved!');
            loadQuizQuestions(modId);
        } else {
            Toast.error(res.message || 'Failed to save question.');
        }
    } catch(err) { Toast.error('Network error. Please try again.'); }
}

document.addEventListener('DOMContentLoaded', loadModules);
</script>
</body>
</html>
