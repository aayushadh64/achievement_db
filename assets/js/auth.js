/**
 * AchieveHub — Auth JS
 * Handles login and registration form submissions via fetch API.
 */

'use strict';

// ─── Password Strength Checker ─────────────────────────────────
function checkPasswordStrength(password) {
  let score = 0;
  if (password.length >= 8)  score++;
  if (password.length >= 12) score++;
  if (/[A-Z]/.test(password)) score++;
  if (/[0-9]/.test(password)) score++;
  if (/[^A-Za-z0-9]/.test(password)) score++;

  if (score <= 1) return { level: 1, label: 'Weak',   cls: 'weak' };
  if (score === 2) return { level: 2, label: 'Fair',   cls: 'fair' };
  if (score === 3) return { level: 3, label: 'Good',   cls: 'good' };
  return              { level: 4, label: 'Strong', cls: 'strong' };
}

function updateStrengthUI(password) {
  const bars  = document.querySelectorAll('.strength-bar');
  const label = document.querySelector('.strength-label');
  if (!bars.length || !label) return;

  const result = checkPasswordStrength(password);

  bars.forEach((bar, i) => {
    bar.className = 'strength-bar';
    if (i < result.level) bar.classList.add(result.cls);
  });

  label.className  = 'strength-label ' + result.cls;
  label.textContent = password.length > 0 ? result.label + ' password' : '';
}

// ─── Form Field Validator ──────────────────────────────────────
function showFieldError(inputEl, message) {
  inputEl.classList.add('error');
  let errEl = inputEl.closest('.input-group, .form-group')?.querySelector('.form-error');
  if (!errEl) {
    errEl = document.createElement('span');
    errEl.className = 'form-error';
    inputEl.closest('.input-group, .form-group')?.appendChild(errEl);
  }
  errEl.textContent = message;
  errEl.style.display = 'flex';
}

function clearFieldError(inputEl) {
  inputEl.classList.remove('error');
  const errEl = inputEl.closest('.input-group, .form-group')?.querySelector('.form-error');
  if (errEl) errEl.style.display = 'none';
}

function clearAllErrors(form) {
  form.querySelectorAll('.form-input').forEach(clearFieldError);
  const alert = form.querySelector('.form-alert');
  if (alert) alert.remove();
}

function showFormAlert(form, type, message) {
  let alert = form.querySelector('.form-alert');
  if (!alert) {
    alert = document.createElement('div');
    form.prepend(alert);
  }
  alert.className  = `form-alert form-alert-${type}`;
  alert.innerHTML  = `<span>${type === 'error' ? '⚠️' : '✅'}</span> ${escapeHtml(message)}`;
}

// ─── Password Toggle ───────────────────────────────────────────
document.querySelectorAll('.password-toggle').forEach((btn) => {
  btn.addEventListener('click', () => {
    const input = btn.closest('.input-icon-wrapper')?.querySelector('input');
    if (!input) return;
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';
    btn.textContent = isPassword ? '🙈' : '👁️';
  });
});

// ─── Login Form ────────────────────────────────────────────────
const loginForm = document.getElementById('login-form');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearAllErrors(loginForm);

    const emailInput = loginForm.querySelector('#email');
    const passInput  = loginForm.querySelector('#password');
    const submitBtn  = loginForm.querySelector('#login-btn');

    const email    = emailInput.value.trim();
    const password = passInput.value;

    // Client-side validation
    let hasError = false;
    if (!email) {
      showFieldError(emailInput, 'Email is required.');
      hasError = true;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showFieldError(emailInput, 'Please enter a valid email.');
      hasError = true;
    }
    if (!password) {
      showFieldError(passInput, 'Password is required.');
      hasError = true;
    }
    if (hasError) return;

    setButtonLoading(submitBtn, true);

    const formData = new FormData();
    formData.append('action',     'login');
    formData.append('email',      email);
    formData.append('password',   password);
    formData.append('csrf_token', App.csrfToken);

    const res = await apiFetch('/api/auth.php', { method: 'POST', body: formData });

    setButtonLoading(submitBtn, false);

    if (res.success) {
      Toast.success(res.message);
      submitBtn.textContent = '🎉 Redirecting…';
      submitBtn.disabled = true;
      setTimeout(() => { window.location.href = res.redirect; }, 800);
    } else {
      showFormAlert(loginForm, 'error', res.message);
    }
  });

  // Clear errors on input
  loginForm.querySelectorAll('.form-input').forEach((input) => {
    input.addEventListener('input', () => clearFieldError(input));
  });
}

// ─── Register Form ─────────────────────────────────────────────
const registerForm = document.getElementById('register-form');
if (registerForm) {
  // Real-time password strength
  const passInput = registerForm.querySelector('#password');
  if (passInput) {
    passInput.addEventListener('input', () => {
      updateStrengthUI(passInput.value);
    });
  }

  registerForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearAllErrors(registerForm);

    const nameInput    = registerForm.querySelector('#name');
    const usernameInput= registerForm.querySelector('#username');
    const emailInput   = registerForm.querySelector('#email');
    const passInput    = registerForm.querySelector('#password');
    const confirmInput = registerForm.querySelector('#confirm_password');
    const submitBtn    = registerForm.querySelector('#register-btn');

    const name            = nameInput.value.trim();
    const username        = usernameInput.value.trim();
    const email           = emailInput.value.trim();
    const password        = passInput.value;
    const confirmPassword = confirmInput.value;

    // Validate
    let hasError = false;
    if (name.length < 2) {
      showFieldError(nameInput, 'Name must be at least 2 characters.');
      hasError = true;
    }
    if (!/^[a-z0-9_]{3,30}$/.test(username.toLowerCase())) {
      showFieldError(usernameInput, 'Username: 3-30 chars, lowercase letters, numbers, underscores only.');
      hasError = true;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      showFieldError(emailInput, 'Please enter a valid email.');
      hasError = true;
    }
    if (password.length < 8) {
      showFieldError(passInput, 'Password must be at least 8 characters.');
      hasError = true;
    }
    if (password !== confirmPassword) {
      showFieldError(confirmInput, 'Passwords do not match.');
      hasError = true;
    }
    if (hasError) return;

    setButtonLoading(submitBtn, true);

    const formData = new FormData();
    formData.append('action',           'register');
    formData.append('name',             name);
    formData.append('username',         username.toLowerCase());
    formData.append('email',            email);
    formData.append('password',         password);
    formData.append('confirm_password', confirmPassword);
    formData.append('csrf_token',       App.csrfToken);

    const res = await apiFetch('/api/auth.php', { method: 'POST', body: formData });

    setButtonLoading(submitBtn, false);

    if (res.success) {
      Toast.success(res.message);
      submitBtn.textContent = '✅ Account Created!';
      submitBtn.disabled = true;
      setTimeout(() => { window.location.href = res.redirect; }, 1200);
    } else {
      showFormAlert(registerForm, 'error', res.message);
    }
  });

  // Clear errors on input
  registerForm.querySelectorAll('.form-input').forEach((input) => {
    input.addEventListener('input', () => clearFieldError(input));
  });
}
