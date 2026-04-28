/**
 * AchieveHub — Main JavaScript v2.1
 * Shared utilities: API fetch wrapper, toast system, modals, loaders
 */

'use strict';

// ─── Config ───────────────────────────────────────────────────
const App = {
  baseUrl:   document.querySelector('meta[name="base-url"]')?.content  || '',
  csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
};

// Keep CSRF token in sync (token can be refreshed server-side)
function refreshCsrfToken() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) App.csrfToken = meta.content;
}

// ─── API Fetch Wrapper ─────────────────────────────────────────
/**
 * Wrapper around fetch that automatically adds CSRF header,
 * handles JSON parsing, and normalizes errors.
 */
async function apiFetch(url, options = {}) {
  refreshCsrfToken();

  const defaults = {
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': App.csrfToken,
    },
  };

  // Merge headers
  if (options.headers) {
    Object.assign(defaults.headers, options.headers);
  }

  const config = { ...defaults, ...options };

  // FormData: browser sets Content-Type with boundary automatically
  if (options.body instanceof FormData) {
    delete config.headers['Content-Type'];
    // Always inject CSRF into FormData for POST
    if (!options.body.has('csrf_token')) {
      options.body.append('csrf_token', App.csrfToken);
    }
  } else if (options.body && typeof options.body === 'object' && !(options.body instanceof URLSearchParams)) {
    // Inject CSRF into object before stringifying
    if (!options.body.hasOwnProperty('csrf_token')) {
      options.body.csrf_token = App.csrfToken;
    }
    config.headers['Content-Type'] = 'application/json';
    config.body = JSON.stringify(options.body);
  } else if (options.body instanceof URLSearchParams) {
    config.headers['Content-Type'] = 'application/x-www-form-urlencoded';
    if (!options.body.has('csrf_token')) {
      options.body.append('csrf_token', App.csrfToken);
    }
  }

  try {
    const response = await fetch(App.baseUrl + url, config);

    // Handle non-JSON responses gracefully
    const contentType = response.headers.get('Content-Type') || '';
    if (!contentType.includes('application/json')) {
      return { success: false, message: `Server error (HTTP ${response.status}).` };
    }

    const data = await response.json();

    // Surface CSRF errors clearly
    if (response.status === 403 && data.message?.includes('CSRF')) {
      return { success: false, message: 'Session expired. Please refresh the page and try again.' };
    }

    return data;
  } catch (err) {
    console.error('[apiFetch] Network error:', err);
    return { success: false, message: 'Network error. Please check your connection.' };
  }
}

// ─── Toast Notifications ───────────────────────────────────────
const Toast = (() => {
  let container = null;

  function getContainer() {
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      document.body.appendChild(container);
    }
    return container;
  }

  const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };

  function show(type, message, duration = 4000) {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <span class="toast-icon">${icons[type] || 'ℹ️'}</span>
      <div class="toast-content">
        <div class="toast-title">${capitalize(type)}</div>
        <div class="toast-message">${escapeHtml(message)}</div>
      </div>
      <button class="toast-close" aria-label="Dismiss">✕</button>
    `;

    toast.querySelector('.toast-close').addEventListener('click', () => dismiss(toast));
    getContainer().appendChild(toast);

    if (duration > 0) {
      setTimeout(() => dismiss(toast), duration);
    }

    return toast;
  }

  function dismiss(toast) {
    if (!toast || toast.classList.contains('removing')) return;
    toast.classList.add('removing');
    toast.addEventListener('animationend', () => toast.remove(), { once: true });
    setTimeout(() => toast.remove(), 400);
  }

  return {
    show,
    success: (m, d) => show('success', m, d),
    error:   (m, d) => show('error',   m, d),
    warning: (m, d) => show('warning', m, d),
    info:    (m, d) => show('info',    m, d),
  };
})();

// ─── Modal Manager ─────────────────────────────────────────────
const Modal = (() => {
  function open(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';

    const onOverlayClick = (e) => {
      if (e.target === overlay) close(id);
    };
    const onEscKey = (e) => {
      if (e.key === 'Escape') close(id);
    };

    overlay.addEventListener('click', onOverlayClick, { once: true });
    document.addEventListener('keydown', onEscKey, { once: true });
  }

  function close(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  function toggle(id) {
    const overlay = document.getElementById(id);
    if (!overlay) return;
    overlay.classList.contains('active') ? close(id) : open(id);
  }

  return { open, close, toggle };
})();

// ─── Button Loading State ──────────────────────────────────────
function setButtonLoading(btn, loading) {
  if (!btn) return;
  if (loading) {
    btn.disabled = true;
    btn.classList.add('loading');
    btn.dataset.originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-sm"></span>';
  } else {
    btn.disabled = false;
    btn.classList.remove('loading');
    if (btn.dataset.originalText) {
      btn.innerHTML = btn.dataset.originalText;
    }
  }
}

// ─── Page Loader ───────────────────────────────────────────────
function hidePageLoader() {
  const loader = document.getElementById('page-loader');
  if (loader) {
    loader.classList.add('fade-out');
    setTimeout(() => loader.remove(), 400);
  }
}

// ─── String Utilities ──────────────────────────────────────────
function escapeHtml(str) {
  if (str == null) return '';
  const div = document.createElement('div');
  div.textContent = String(str);
  return div.innerHTML;
}

function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function truncate(str, max = 80) {
  if (!str) return '';
  return str.length > max ? str.slice(0, max) + '…' : str;
}

// ─── Number Animation ──────────────────────────────────────────
function animateNumber(el, target, duration = 1500) {
  const startTime = performance.now();

  function update(currentTime) {
    const elapsed  = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    const eased    = 1 - Math.pow(1 - progress, 4);
    el.textContent = Math.floor(eased * target).toLocaleString();
    if (progress < 1) requestAnimationFrame(update);
  }

  requestAnimationFrame(update);
}

// ─── Intersection Observer (animate on scroll) ─────────────────
function observeElements(selector, className = 'animate-slide-up') {
  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add(className);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1 });

  document.querySelectorAll(selector).forEach((el) => observer.observe(el));
}

// ─── Flash Message from Session ────────────────────────────────
function showFlashFromPage() {
  const flashEl = document.getElementById('flash-data');
  if (!flashEl) return;
  const type    = flashEl.dataset.type;
  const message = flashEl.dataset.message;
  if (type && message) {
    Toast.show(type, message);
    flashEl.remove();
  }
}

// ─── Sidebar Mobile Toggle ─────────────────────────────────────
function initSidebar() {
  const toggle  = document.getElementById('menu-toggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebar-overlay');

  if (!toggle || !sidebar) return;

  toggle.addEventListener('click', () => {
    const isOpen = sidebar.classList.toggle('open');
    overlay?.classList.toggle('open', isOpen);
    toggle.setAttribute('aria-expanded', String(isOpen));
  });

  overlay?.addEventListener('click', () => {
    sidebar.classList.remove('open');
    overlay.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
  });
}

// ─── Confirm Dialog ────────────────────────────────────────────
function confirmAction(message) {
  return window.confirm(message);
}

// ─── Debounce ──────────────────────────────────────────────────
function debounce(fn, wait = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };
}

// ─── Image Preview Helper ──────────────────────────────────────
function initImagePreview(inputId, previewId) {
  const input   = document.getElementById(inputId);
  const preview = document.getElementById(previewId);
  if (!input || !preview) return;

  input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) { preview.style.display = 'none'; return; }

    if (!file.type.startsWith('image/')) {
      Toast.error('Please select a valid image file (JPG, PNG, WEBP).');
      input.value = '';
      preview.style.display = 'none';
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      Toast.error('Image must be 5MB or smaller.');
      input.value = '';
      preview.style.display = 'none';
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      preview.src     = e.target.result;
      preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
  });
}

// ─── Init on DOM Ready ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  hidePageLoader();
  showFlashFromPage();
  initSidebar();
  observeElements('.glass-card');
});
