/**
 * AchieveHub — Admin Dashboard JS v2.1
 * Stats loading, course management, thumbnail upload
 */

'use strict';

// ─── Load Stats ────────────────────────────────────────────────
async function loadStats() {
  try {
    const res = await apiFetch('/api/stats.php');
    if (!res.success) return;

    const { stats, top_courses, recent_users, enrollment_trend } = res;

    const statMap = {
      'stat-users':       stats.total_users,
      'stat-courses':     stats.total_courses,
      'stat-enrollments': stats.total_enrollments,
      'stat-completed':   stats.total_completed,
    };

    Object.entries(statMap).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (el) animateNumber(el, parseInt(val) || 0);
    });

    renderEnrollmentChart(enrollment_trend);
    renderTopCourses(top_courses);
    renderRecentUsers(recent_users);
  } catch (err) {
    console.error('[loadStats]', err);
  }
}

// ─── Enrollment Trend Chart ────────────────────────────────────
function renderEnrollmentChart(data) {
  const canvas = document.getElementById('enrollment-chart');
  if (!canvas || typeof Chart === 'undefined') return;

  const labels = (data || []).map(d => {
    const date = new Date(d.day);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
  });
  const values = (data || []).map(d => parseInt(d.count));

  new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Enrollments',
        data: values,
        borderColor: '#6c63ff',
        backgroundColor: 'rgba(108,99,255,0.12)',
        fill: true,
        tension: 0.4,
        pointBackgroundColor: '#6c63ff',
        pointRadius: 4,
        pointHoverRadius: 6,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { intersect: false, mode: 'index' },
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: 'rgba(14,14,46,0.95)',
          borderColor: 'rgba(255,255,255,0.1)',
          borderWidth: 1,
          titleColor: '#f1f5f9',
          bodyColor: '#94a3b8',
        },
      },
      scales: {
        x: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#475569', font: { size: 11 } } },
        y: { grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#475569', font: { size: 11 }, stepSize: 1 }, beginAtZero: true },
      },
    },
  });
}

// ─── Top Courses Table ─────────────────────────────────────────
function renderTopCourses(courses) {
  const tbody = document.getElementById('top-courses-body');
  if (!tbody) return;

  if (!courses?.length) {
    tbody.innerHTML = `<tr><td colspan="2" class="text-center text-muted" style="padding:2rem">No courses yet.</td></tr>`;
    return;
  }

  tbody.innerHTML = courses.map((c, i) => `
    <tr>
      <td>
        <div style="display:flex;align-items:center;gap:.75rem">
          <span style="font-size:.75rem;font-weight:700;color:var(--color-text-muted);min-width:20px">#${i + 1}</span>
          <span style="font-weight:500;color:var(--color-text-primary)">${escapeHtml(c.title)}</span>
        </div>
      </td>
      <td style="text-align:right">
        <span class="badge badge-purple">${c.enrollments} enrolled</span>
      </td>
    </tr>
  `).join('');
}

// ─── Recent Users ──────────────────────────────────────────────
function renderRecentUsers(users) {
  const container = document.getElementById('recent-users');
  if (!container) return;

  if (!users?.length) {
    container.innerHTML = `<p class="text-muted text-center">No users yet.</p>`;
    return;
  }

  container.innerHTML = users.map(u => `
    <div style="display:flex;align-items:center;gap:.75rem;padding:.75rem 0;border-bottom:1px solid var(--color-border)">
      <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:.75rem">
        ${escapeHtml((u.name || '?').charAt(0).toUpperCase())}
      </div>
      <div style="flex:1;min-width:0">
        <div style="font-size:.875rem;font-weight:600;color:var(--color-text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(u.name)}</div>
        <div style="font-size:.75rem;color:var(--color-text-muted)">${escapeHtml(u.email)}</div>
      </div>
    </div>
  `).join('');
}

// ─── Course Management Table ───────────────────────────────────
let coursesPage = 1;

async function loadCoursesTable(page = 1) {
  const tbody = document.getElementById('courses-table-body');
  if (!tbody) return;

  coursesPage = page;
  const search = document.getElementById('course-search')?.value || '';
  tbody.innerHTML = `<tr><td colspan="5" class="text-center" style="padding:2rem"><div class="spinner" style="margin:auto;width:32px;height:32px"></div></td></tr>`;

  try {
    const res = await apiFetch(`/api/courses.php?action=list&page=${page}&search=${encodeURIComponent(search)}`);

    if (!res.success) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-error" style="padding:1.5rem">${escapeHtml(res.message)}</td></tr>`;
      return;
    }

    if (!res.courses.length) {
      tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted" style="padding:2rem">No courses found.</td></tr>`;
      renderPagination(1, 1);
      return;
    }

    tbody.innerHTML = res.courses.map(c => `
      <tr data-id="${c.id}">
        <td>
          <div style="display:flex;align-items:center;gap:.75rem">
            ${c.thumbnail
              ? `<img src="${escapeHtml(c.thumbnail)}" alt="thumb" style="width:40px;height:40px;object-fit:cover;border-radius:.25rem;flex-shrink:0">`
              : `<div style="width:40px;height:40px;background:rgba(255,255,255,0.05);border-radius:.25rem;display:flex;align-items:center;justify-content:center;flex-shrink:0">📚</div>`
            }
            <div>
              <div style="font-weight:600;color:var(--color-text-primary)">${escapeHtml(c.title)}</div>
              <div style="font-size:.75rem;color:var(--color-text-muted);margin-top:2px">${escapeHtml(truncate(c.description || '', 60))}</div>
            </div>
          </div>
        </td>
        <td><span class="badge badge-purple">${c.module_count} Modules</span></td>
        <td>${escapeHtml(c.author || '')}</td>
        <td>${escapeHtml(c.created_at_fmt || '')}</td>
        <td>
          <div style="display:flex;gap:.5rem;flex-wrap:wrap">
            <a class="btn btn-primary btn-sm" href="manage_course.php?id=${c.id}">📝 Manage</a>
            <button class="btn btn-sm" style="background:rgba(99,102,241,.2);color:#818cf8;border:1px solid rgba(99,102,241,.3)" onclick="openThumbnailModal(${c.id}, '${escapeHtml(c.title).replace(/'/g,"\\'")}')">🖼 Thumb</button>
            <button class="btn btn-danger btn-sm" onclick="deleteCourse(${c.id}, this)">🗑</button>
          </div>
        </td>
      </tr>
    `).join('');

    renderPagination(res.page, res.total_pages);
  } catch (err) {
    console.error('[loadCoursesTable]', err);
    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-error" style="padding:1.5rem">Failed to load courses.</td></tr>`;
  }
}

function renderPagination(current, total) {
  const pag = document.getElementById('courses-pagination');
  if (!pag || total <= 1) { if (pag) pag.innerHTML = ''; return; }

  let html = `<div style="display:flex;gap:.5rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap">`;
  for (let i = 1; i <= total; i++) {
    html += `<button class="btn btn-sm ${i === current ? 'btn-primary' : 'btn-secondary'}" onclick="coursesPage=${i};loadCoursesTable(${i})">${i}</button>`;
  }
  html += '</div>';
  pag.innerHTML = html;
}

// ─── Thumbnail Modal ───────────────────────────────────────────
let thumbnailTargetId = null;

function openThumbnailModal(courseId, title) {
  thumbnailTargetId = courseId;
  const label = document.getElementById('thumb-modal-course-name');
  if (label) label.textContent = title;
  const preview = document.getElementById('thumb-preview');
  if (preview) { preview.src = ''; preview.style.display = 'none'; }
  const input = document.getElementById('thumb-file-input');
  if (input) input.value = '';
  Modal.open('thumbnail-modal');
}

async function uploadThumbnail() {
  const input = document.getElementById('thumb-file-input');
  const btn   = document.getElementById('thumb-upload-btn');

  if (!input?.files.length) {
    Toast.warning('Please select an image file first.');
    return;
  }

  setButtonLoading(btn, true);

  const formData = new FormData();
  formData.append('thumbnail', input.files[0]);
  formData.append('course_id', thumbnailTargetId);

  try {
    const res = await apiFetch(`/api/courses.php?action=update_thumbnail`, { method: 'POST', body: formData });

    setButtonLoading(btn, false);

    if (res.success) {
      Toast.success('Thumbnail updated!');
      Modal.close('thumbnail-modal');
      loadCoursesTable(coursesPage);
    } else {
      Toast.error(res.message || 'Upload failed.');
    }
  } catch (err) {
    Toast.error('Network error. Please try again.');
    setButtonLoading(btn, false);
  }
}

// ─── Delete Course ─────────────────────────────────────────────
async function deleteCourse(id, btn) {
  if (!confirmAction('Remove this course? This cannot be undone.')) return;

  setButtonLoading(btn, true);

  const formData = new FormData();
  formData.append('course_id', id);

  const res = await apiFetch('/api/courses.php?action=delete', { method: 'POST', body: formData });

  if (res.success) {
    Toast.success(res.message);
    const row = btn.closest('tr');
    if (row) {
      row.style.opacity = '0';
      row.style.transition = 'opacity 0.3s ease';
      setTimeout(() => row.remove(), 300);
    }
  } else {
    setButtonLoading(btn, false);
    Toast.error(res.message || 'Delete failed.');
  }
}

// ─── Add Course Form ───────────────────────────────────────────
const addCourseForm = document.getElementById('add-course-form');
if (addCourseForm) {
  // Wire up image preview
  document.addEventListener('DOMContentLoaded', () => {
    initImagePreview('add-course-thumbnail', 'add-course-thumb-preview');
  });

  addCourseForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = document.getElementById('add-course-btn');
    setButtonLoading(submitBtn, true);

    const formData = new FormData(addCourseForm);

    const res = await apiFetch('/api/courses.php?action=add', { method: 'POST', body: formData });

    setButtonLoading(submitBtn, false);

    if (res.success) {
      Toast.success(res.message);
      Modal.close('add-course-modal');
      addCourseForm.reset();
      const preview = document.getElementById('add-course-thumb-preview');
      if (preview) { preview.src = ''; preview.style.display = 'none'; }
      loadCoursesTable(1);
      loadStats();
    } else {
      Toast.error(res.message || 'Failed to create course.');
    }
  });
}

// ─── Search ────────────────────────────────────────────────────
document.getElementById('course-search')?.addEventListener('input', debounce(() => {
  loadCoursesTable(1);
}, 400));

// ─── Init ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  loadStats();
  loadCoursesTable(1);

  // Wire thumbnail preview in standalone modal
  const thumbInput = document.getElementById('thumb-file-input');
  if (thumbInput) {
    thumbInput.addEventListener('change', () => {
      const file    = thumbInput.files[0];
      const preview = document.getElementById('thumb-preview');
      if (!file || !preview) return;

      if (!file.type.startsWith('image/')) {
        Toast.error('Please select an image (JPG, PNG, WEBP).');
        thumbInput.value = '';
        preview.style.display = 'none';
        return;
      }
      if (file.size > 5 * 1024 * 1024) {
        Toast.error('Image must be 5MB or smaller.');
        thumbInput.value = '';
        preview.style.display = 'none';
        return;
      }

      const reader = new FileReader();
      reader.onload = (ev) => {
        preview.src           = ev.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(file);
    });
  }
});
