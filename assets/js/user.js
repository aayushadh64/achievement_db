/**
 * AchieveHub — User JS v2.1
 * Playlists, Modules, Quizzes, and Achievements
 */
'use strict';

let currentPage     = 1;
let currentCourseId = null;
let currentModuleId = null;

// ─── Load Courses ──────────────────────────────────────────────
async function loadCourses(page = 1) {
  const grid = document.getElementById('course-grid');
  if (!grid) return;

  currentPage = page;
  const search = document.getElementById('course-search')?.value || '';
  grid.innerHTML = '<div class="spinner" style="grid-column:1/-1;margin:2rem auto"></div>';

  try {
    const res = await apiFetch(`/api/courses.php?action=list&page=${page}&search=${encodeURIComponent(search)}`);

    if (!res.success) {
      grid.innerHTML = `<div class="text-error" style="grid-column:1/-1;text-align:center;padding:3rem">${escapeHtml(res.message)}</div>`;
      return;
    }

    if (!res.courses.length) {
      grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:3rem;color:var(--color-text-muted)">No playlists found.</div>`;
      renderPagination(1, 1);
      return;
    }

    grid.innerHTML = res.courses.map(c => `
      <div class="glass-card course-card animate-fade-in" style="display:flex;flex-direction:column">
        <div class="course-thumb" style="height:160px;background:linear-gradient(135deg,#1a1c29,#12131f);position:relative;overflow:hidden;border-radius:.75rem .75rem 0 0">
          ${c.thumbnail
            ? `<img src="${escapeHtml(c.thumbnail)}" alt="Course thumbnail"
                    style="width:100%;height:100%;object-fit:cover;opacity:0.85"
                    onerror="this.style.display='none'">`
            : `<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2.5rem">📚</div>`
          }
          <div style="position:absolute;bottom:0;left:0;right:0;padding:.5rem .75rem;background:linear-gradient(transparent,rgba(0,0,0,0.75))">
            <span class="badge badge-purple">${c.module_count} Modules</span>
          </div>
        </div>
        <div class="course-body" style="padding:1.5rem;flex:1;display:flex;flex-direction:column">
          <h3 style="margin:0 0 .5rem;font-size:1.1rem;color:var(--color-text-primary)">${escapeHtml(c.title)}</h3>
          <p style="font-size:.875rem;color:var(--color-text-muted);margin:0 0 1rem;flex:1">${escapeHtml(truncate(c.description || '', 90))}</p>

          ${c.progress !== null
            ? `<div style="margin-bottom:1rem">
                <div style="display:flex;justify-content:space-between;font-size:.75rem;margin-bottom:.25rem;color:var(--color-text-muted)">
                  <span>Progress</span><span>${c.progress}%</span>
                </div>
                <div class="progress-bar-container">
                  <div class="progress-bar-fill" style="width:${c.progress}%"></div>
                </div>
               </div>
               <button class="btn btn-primary w-full" onclick="openPlaylist(${c.id}, '${escapeHtml(c.title).replace(/'/g,"\\'")}')">
                 ▶ ${c.progress > 0 ? 'Resume' : 'Start'} Playlist
               </button>`
            : `<button class="btn btn-secondary w-full" onclick="enrollCourse(${c.id}, this)">
                 Enroll Now
               </button>`
          }
        </div>
      </div>
    `).join('');

    renderPagination(res.page, res.total_pages);
  } catch (err) {
    console.error('[loadCourses]', err);
    grid.innerHTML = '<div class="text-error" style="grid-column:1/-1;text-align:center;padding:3rem">Failed to load playlists. Please try again.</div>';
  }
}

function renderPagination(current, total) {
  const pag = document.getElementById('courses-pagination');
  if (!pag || total <= 1) { if (pag) pag.innerHTML = ''; return; }

  let html = `<div style="display:flex;gap:.5rem;justify-content:center;margin-top:2rem;flex-wrap:wrap">`;
  for (let i = 1; i <= total; i++) {
    html += `<button class="btn btn-sm ${i === current ? 'btn-primary' : 'btn-secondary'}" onclick="loadCourses(${i})">${i}</button>`;
  }
  html += '</div>';
  pag.innerHTML = html;
}

// ─── Enroll ────────────────────────────────────────────────────
async function enrollCourse(id, btn) {
  setButtonLoading(btn, true);
  try {
    const formData = new FormData();
    formData.append('course_id', id);

    const res = await apiFetch('/api/courses.php?action=enroll', { method: 'POST', body: formData });
    if (res.success) {
      Toast.success('Enrolled! Opening playlist…');
      await loadCourses(currentPage);
      // Auto-open the playlist
      const courses = document.querySelectorAll('.course-card');
      // brief delay to let DOM update
    } else {
      Toast.error(res.message || 'Failed to enroll.');
      setButtonLoading(btn, false);
    }
  } catch (err) {
    Toast.error('Network error. Please try again.');
    setButtonLoading(btn, false);
  }
}

// ─── Load Achievements ─────────────────────────────────────────
async function loadAchievements() {
  const container = document.getElementById('achievements-container');
  if (!container) return;

  try {
    const res = await apiFetch('/api/achievements.php');
    if (!res.success || !res.achievements?.length) {
      container.innerHTML = '<div class="text-muted" style="padding:.5rem 0">Earn achievements by completing playlists! 🎯</div>';
      return;
    }

    container.innerHTML = res.achievements.map(a => `
      <div style="background:rgba(255,255,255,0.05);border:1px solid var(--color-cyan);border-radius:.5rem;padding:1rem;width:220px;text-align:center;transition:transform .2s ease" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform=''">
        <div style="font-size:2rem;margin-bottom:.5rem">${escapeHtml(a.icon || '🏆')}</div>
        <div style="font-weight:600;font-size:.9rem;color:var(--color-cyan)">${escapeHtml(a.title)}</div>
        <div style="font-size:.75rem;color:var(--color-text-muted);margin-top:.25rem">${escapeHtml(a.date || '')}</div>
      </div>
    `).join('');
  } catch (err) {
    // Silently ignore — achievements are secondary
  }
}

// ─── Playlist Viewer ───────────────────────────────────────────
let playlistModules = [];

async function openPlaylist(id, title) {
  currentCourseId = id;
  document.getElementById('viewer-title').textContent = title;

  const sidebar = document.getElementById('playlist-sidebar');
  const viewer  = document.getElementById('viewer-content');

  sidebar.innerHTML = '<div class="spinner" style="margin:2rem auto"></div>';
  viewer.innerHTML  = '';

  Modal.open('course-viewer-modal');

  try {
    const res = await apiFetch(`/api/modules.php?course_id=${id}`);

    if (!res.success) {
      sidebar.innerHTML = `<div class="text-error" style="padding:1rem">${escapeHtml(res.message)}</div>`;
      return;
    }

    playlistModules = res.modules || [];

    if (!playlistModules.length) {
      sidebar.innerHTML = '<div class="text-muted" style="padding:1rem">No modules in this playlist yet.</div>';
      return;
    }

    renderSidebar();

    // Start at first incomplete module, fall back to first
    const nextMod = playlistModules.find(m => m.is_completed === 0) || playlistModules[0];
    playModule(nextMod);

  } catch (err) {
    console.error('[openPlaylist]', err);
    sidebar.innerHTML = '<div class="text-error" style="padding:1rem">Failed to load modules.</div>';
  }
}

function renderSidebar() {
  const sidebar = document.getElementById('playlist-sidebar');
  sidebar.innerHTML = playlistModules.map((m, idx) => `
    <div class="playlist-item ${currentModuleId === m.id ? 'active' : ''} ${m.is_completed ? 'completed' : ''}"
         onclick="playModuleById(${m.id})" role="button" tabindex="0">
      <div class="item-title">
        <span>${idx + 1}. ${escapeHtml(m.title)}</span>
        ${m.is_completed ? '<span style="color:#00E676;flex-shrink:0">✓</span>' : ''}
      </div>
      <div class="item-type">
        ${m.type === 'youtube' ? '▶ VIDEO' : m.type === 'ebook' ? '📄 EBOOK' : '📝 QUIZ'}
      </div>
    </div>
  `).join('');
}

function playModuleById(id) {
  const mod = playlistModules.find(m => m.id === id);
  if (mod) playModule(mod);
}

function playModule(mod) {
  currentModuleId = mod.id;
  renderSidebar();

  const viewer = document.getElementById('viewer-content');
  viewer.innerHTML = '';

  if (mod.type === 'youtube') {
    const videoId = extractYouTubeId(mod.content_data || mod.youtube_id || '');
    if (!videoId) {
      viewer.innerHTML = '<div style="padding:2rem;color:var(--color-text-muted)">Video URL is not configured.</div>';
      appendMarkCompleteBtn(viewer, mod);
      return;
    }
    const iframe = document.createElement('iframe');
    iframe.style.cssText = 'width:100%;height:100%;border:none';
    iframe.src = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    iframe.allowFullscreen = true;
    viewer.appendChild(iframe);
    if (!mod.is_completed) appendMarkCompleteBtn(viewer, mod);

  } else if (mod.type === 'ebook') {
    const url = App.baseUrl + '/' + (mod.content_data || '');
    viewer.innerHTML = `<iframe src="${escapeHtml(url)}" style="width:100%;height:100%;border:none;background:#fff"></iframe>`;
    if (!mod.is_completed) appendMarkCompleteBtn(viewer, mod);

  } else if (mod.type === 'quiz') {
    renderQuiz(mod);
  }
}

function extractYouTubeId(urlOrId) {
  if (!urlOrId) return '';
  // Already an ID (11 chars, no slashes)
  if (/^[a-zA-Z0-9_-]{11}$/.test(urlOrId)) return urlOrId;
  // Try parsing URL
  try {
    const url = new URL(urlOrId);
    if (url.hostname.includes('youtu.be')) return url.pathname.slice(1);
    return url.searchParams.get('v') || '';
  } catch {
    return '';
  }
}

function appendMarkCompleteBtn(container, mod) {
  if (mod.is_completed) return;
  const btn = document.createElement('button');
  btn.className = 'btn btn-primary';
  btn.textContent = '✓ Mark as Completed';
  btn.style.cssText = 'position:absolute;bottom:1.5rem;right:1.5rem;z-index:100;box-shadow:0 8px 24px rgba(0,0,0,0.5)';
  btn.onclick = () => markModuleComplete(mod.id, btn);
  container.appendChild(btn);
}

async function markModuleComplete(modId, btn) {
  setButtonLoading(btn, true);
  try {
    const formData = new FormData();
    formData.append('module_id', modId);
    formData.append('course_id', currentCourseId);

    const res = await apiFetch('/api/modules.php?action=progress', { method: 'POST', body: formData });

    if (!res.success) {
      Toast.error(res.message || 'Could not mark as complete.');
      setButtonLoading(btn, false);
      return;
    }

    const m = playlistModules.find(x => x.id === modId);
    if (m) m.is_completed = 1;

    btn.remove();
    renderSidebar();
    loadCourses(currentPage);
    loadAchievements();
    Toast.success('Module completed! 🎉');

  } catch (err) {
    Toast.error('Network error. Please try again.');
    setButtonLoading(btn, false);
  }
}

// ─── Quiz Player ───────────────────────────────────────────────
async function renderQuiz(mod) {
  const viewer = document.getElementById('viewer-content');

  // Loading state
  viewer.innerHTML = `
    <div class="quiz-container" style="display:flex;align-items:center;justify-content:center;height:100%">
      <div style="text-align:center">
        <div class="spinner" style="margin:0 auto 1rem;border-color:var(--color-cyan)"></div>
        <p style="color:var(--color-text-muted)">Loading quiz…</p>
      </div>
    </div>`;

  // Guard: module_id must be valid
  if (!mod.id || mod.id < 1) {
    viewer.innerHTML = `<div class="quiz-container"><div class="text-error">Invalid quiz module.</div></div>`;
    return;
  }

  let res;
  try {
    res = await apiFetch(`/api/quizzes.php?action=load&module_id=${mod.id}`);
  } catch (err) {
    viewer.innerHTML = `
      <div class="quiz-container">
        <div class="text-error">Failed to connect to the server. Please try again.</div>
        <button class="btn btn-secondary" style="margin-top:1rem" onclick="renderQuiz(playlistModules.find(m=>m.id===${mod.id}))">Retry</button>
      </div>`;
    return;
  }

  if (!res.success) {
    viewer.innerHTML = `
      <div class="quiz-container">
        <div class="text-error">${escapeHtml(res.message || 'Failed to load quiz.')}</div>
        <button class="btn btn-secondary" style="margin-top:1rem" onclick="renderQuiz(playlistModules.find(m=>m.id===${mod.id}))">Retry</button>
      </div>`;
    return;
  }

  // No questions yet
  if (!res.questions || res.questions.length === 0) {
    viewer.innerHTML = `
      <div class="quiz-container" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;height:100%">
        <div style="font-size:3rem;margin-bottom:1rem">📝</div>
        <h3 style="margin:0 0 .5rem">No Questions Yet</h3>
        <p style="color:var(--color-text-muted)">This quiz doesn't have any questions. Check back later!</p>
        ${!mod.is_completed ? `<button class="btn btn-primary" style="margin-top:1.5rem" onclick="markModuleComplete(${mod.id}, this)">Mark as Complete</button>` : ''}
      </div>`;
    return;
  }

  // Render questions
  let html = `<div class="quiz-container"><h2 style="margin-top:0;margin-bottom:2rem;font-size:1.4rem">${escapeHtml(mod.title)}</h2>`;
  html += `<div id="quiz-form">`;

  res.questions.forEach((q, i) => {
    html += `
      <div class="quiz-question" data-qid="${q.id}">
        <h4 style="margin-top:0;font-size:1.05rem;line-height:1.5">${i + 1}. ${escapeHtml(q.question_text)}</h4>
        <div style="display:flex;flex-direction:column;gap:.5rem;margin-top:1rem">
    `;
    q.options.forEach(opt => {
      html += `
        <label class="quiz-option" style="cursor:pointer">
          <input type="radio" name="ans_${q.id}" value="${opt.id}" style="margin-right:.75rem">
          ${escapeHtml(opt.option_text)}
        </label>`;
    });
    html += `</div></div>`;
  });

  html += `
    <button type="button" class="btn btn-cyan btn-lg w-full" id="quiz-submit-btn" onclick="submitQuiz(${mod.id}, quizQuestions_${mod.id})">
      Submit Answers
    </button>
  </div></div>`;

  viewer.innerHTML = html;

  // Store questions reference on window for onclick handler
  window[`quizQuestions_${mod.id}`] = res.questions;
}

async function submitQuiz(modId, questions) {
  const btn  = document.getElementById('quiz-submit-btn');
  const form = document.getElementById('quiz-form');

  if (!form || !questions) return;

  // Validate all questions answered
  let allAnswered = true;
  const answers   = {};

  questions.forEach(q => {
    const checked = form.querySelector(`input[name="ans_${q.id}"]:checked`);
    if (!checked) {
      allAnswered = false;
    } else {
      answers[q.id] = parseInt(checked.value, 10);
    }
  });

  if (!allAnswered) {
    Toast.warning('Please answer all questions before submitting.');
    return;
  }

  setButtonLoading(btn, true);

  try {
    const res = await apiFetch('/api/quizzes.php?action=submit', {
      method:  'POST',
      body:    { module_id: modId, answers },
    });

    if (!res.success) {
      Toast.error(res.message || 'Submission failed. Please try again.');
      setButtonLoading(btn, false);
      return;
    }

    const viewer = document.getElementById('viewer-content');
    const pct    = res.pct ?? (res.max > 0 ? Math.round((res.score / res.max) * 100) : 100);
    const passed = res.passed;

    viewer.innerHTML = `
      <div class="quiz-container" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;height:100%">
        <div style="font-size:4rem;margin-bottom:1rem">${passed ? '🏆' : '📖'}</div>
        <h2 style="margin:0 0 .5rem">${passed ? 'Quiz Complete!' : 'Keep Practicing!'}</h2>
        <div style="font-size:1.75rem;font-weight:700;color:${passed ? 'var(--color-cyan)' : '#ffa500'};margin-bottom:.5rem">
          ${res.score} / ${res.max} points
        </div>
        <div style="font-size:1rem;color:var(--color-text-muted);margin-bottom:2rem">${pct}% correct</div>
        ${!passed ? `<button class="btn btn-secondary" onclick="renderQuiz(playlistModules.find(m=>m.id===${modId}))">Try Again</button>` : ''}
      </div>`;

    const m = playlistModules.find(x => x.id === modId);
    if (m) m.is_completed = 1;
    renderSidebar();
    loadCourses(currentPage);
    loadAchievements();

  } catch (err) {
    console.error('[submitQuiz]', err);
    Toast.error('Network error. Please try again.');
    setButtonLoading(btn, false);
  }
}

// ─── Search Hook ───────────────────────────────────────────────
document.getElementById('course-search')?.addEventListener('input', debounce(() => {
  loadCourses(1);
}, 400));

// Init
document.addEventListener('DOMContentLoaded', () => {
  loadCourses(1);
  loadAchievements();
});
