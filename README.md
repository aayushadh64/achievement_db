# AchieveHub — Setup Guide & Documentation

## 📋 Project Overview

**AchieveHub** is a production-level course achievement system built with PHP 8+, MySQL (PDO), vanilla CSS (glassmorphism dark theme), and JavaScript (ES2022).

---

## ⚡ Quick Setup

### 1. Requirements
- PHP 8.0 or higher
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` enabled
- XAMPP (for local) or InfinityFree (for hosting)

### 2. Database Setup
1. Open `http://localhost/phpmyadmin`
2. Click **Import** → select `database.sql`
3. Click **Go** — the `achievement_db` database will be created with tables and seed data

Or run via CLI:
```bash
mysql -u root -p < database.sql
```

### 3. Configuration
Edit `config/app.php`:
```php
define('BASE_URL', '/temporary/achievement_system_db'); // Your server path
define('DB_HOST',  'localhost');
define('DB_NAME',  'achievement_db');
define('DB_USER',  'root');
define('DB_PASS',  '');
define('DEBUG_MODE', false); // Set false in production!
```

### 4. File Permissions
```bash
chmod 755 uploads/
chmod 644 .htaccess
```

### 5. Access the Site
- Local: `http://localhost/temporary/achievement_system_db`
- Login: `admin@achievehub.com` / `Admin@123`

> ⚠️ **Change the admin password immediately after first login!**

---

## 📁 Project Structure

```
achievement_system_db/
│
├── .htaccess               ← Security headers, compression, caching
├── index.php               ← Entry redirect
├── database.sql            ← Full schema + seed data
├── README.md               ← This file
│
├── config/
│   ├── app.php             ← App constants (DB, paths, security)
│   └── database.php        ← PDO singleton connection
│
├── includes/
│   ├── functions.php       ← Helpers (sanitize, flash, rate limit, upload)
│   ├── auth.php            ← Login/register/logout logic
│   └── csrf.php            ← CSRF token generation & validation
│
├── api/
│   ├── auth.php            ← POST login/register/logout → JSON
│   ├── courses.php         ← GET/POST courses CRUD → JSON
│   └── stats.php           ← GET admin stats → JSON
│
├── auth/
│   ├── login.php           ← Login page
│   ├── register.php        ← Registration page
│   └── logout.php          ← Logout + session destroy
│
├── admin/
│   └── dashboard.php       ← Admin control panel
│
├── user/
│   └── dashboard.php       ← User course browser
│
├── assets/
│   ├── css/
│   │   ├── main.css        ← Design system, components, utilities
│   │   ├── auth.css        ← Auth page styles
│   │   └── dashboard.css   ← Dashboard layout
│   └── js/
│       ├── main.js         ← Shared: fetch wrapper, toasts, modals
│       ├── auth.js         ← Login/register form logic
│       ├── admin.js        ← Admin AJAX, charts (Chart.js)
│       └── user.js         ← User course browse, enroll, progress
│
└── uploads/
    └── .htaccess           ← Blocks PHP execution in uploads
```

---

## 🔒 Security Features

| Feature | Implementation |
|---------|---------------|
| SQL Injection | PDO prepared statements everywhere |
| XSS | `htmlspecialchars()` on all output + Content-Security-Policy header |
| CSRF | Token per session, validated on every POST/mutation |
| Password Storage | bcrypt (cost 12) via `password_hash()` |
| Session Fixation | `session_regenerate_id(true)` after login |
| File Uploads | Extension + MIME type validation, randomized filenames |
| PHP in Uploads | Blocked via `uploads/.htaccess` + `php_flag engine off` |
| Rate Limiting | DB-backed IP rate limiting (5 attempts / 5 min) |
| Directory Browsing | Disabled via `Options -Indexes` |
| Security Headers | X-Frame-Options, X-XSS-Protection, CSP, X-Content-Type-Options |

---

## 🧪 Testing Checklist

### Authentication
- [ ] Register a new user
- [ ] Login as user → redirects to user dashboard
- [ ] Login as admin → redirects to admin dashboard
- [ ] Logout → session destroyed, redirects to login
- [ ] Try SQL injection in login: `' OR 1=1 --` → should fail
- [ ] Try 6+ wrong logins → rate limit triggers

### Admin
- [ ] Stat cards load with animated numbers
- [ ] Enrollment trend chart renders
- [ ] Course type doughnut chart renders
- [ ] Add YouTube course → appears in table and user dashboard
- [ ] Add ebook course → file uploaded, appears in table
- [ ] Delete course → removed from table (soft delete)
- [ ] Search courses in table

### User
- [ ] Course grid loads with thumbnails
- [ ] Filter by YouTube / Ebook
- [ ] Search courses
- [ ] Enroll in a course → progress bar appears
- [ ] Open YouTube course → video plays in modal
- [ ] Mark course complete → achievement shows in sidebar
- [ ] Load More button paginates results

### Security
- [ ] Navigate to `admin/dashboard.php` as regular user → redirected
- [ ] Navigate to `config/app.php` directly → 403 Forbidden
- [ ] Upload a `.php` file as ebook → rejected
- [ ] Open `uploads/` in browser → 403 Forbidden

---

## 🚀 InfinityFree Deployment

1. **Upload** all files via FileZilla to your InfinityFree `/htdocs/` folder
2. **Database**: Create DB in InfinityFree control panel → Import `database.sql`
3. **Update** `config/app.php`:
   ```php
   define('BASE_URL', '');  // Empty for root domain
   define('DB_HOST', 'sqlXXX.epower.io'); // Your InfinityFree DB host
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   define('DEBUG_MODE', false);
   ```
4. **Update** `RewriteBase` in `.htaccess`:
   ```
   RewriteBase /
   ```
5. Verify the site loads at your domain

---

## 🔧 Default Credentials

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@achievehub.com | Admin@123 |

> **⚠️ Change this immediately in production!**

---

## 📦 Dependencies

| Library | Version | CDN |
|---------|---------|-----|
| Chart.js | 4.4.0 | jsdelivr.net |
| Google Fonts (Inter) | Latest | fonts.googleapis.com |

No npm, no build step — purely CDN.

---

## 🗺️ Migration Path (Node.js / Frameworks)

The codebase is structured to be framework-agnostic:
- API endpoints return clean JSON — drop-in compatible with any frontend framework
- Business logic is in `includes/` — easily portable to Express.js/Fastify services
- CSS uses design tokens (CSS variables) — migrate to Tailwind by mapping tokens
