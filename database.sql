-- ============================================================
--  AchieveHub — Production Database Schema (Playlist Architecture)
--  Engine: InnoDB | Charset: utf8mb4_unicode_ci
--  Run this ONCE to set up the database from scratch.
-- ============================================================

DROP DATABASE IF EXISTS achievement_db;
CREATE DATABASE achievement_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE achievement_db;

-- ─── Users ───────────────────────────────────────────────────────────────────
CREATE TABLE users (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)    NOT NULL,
    username      VARCHAR(50)     NOT NULL UNIQUE,
    email         VARCHAR(150)    NOT NULL UNIQUE,
    password      VARCHAR(255)    NOT NULL,
    role          ENUM('admin','user') NOT NULL DEFAULT 'user',
    avatar        VARCHAR(255)    DEFAULT NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    last_login    DATETIME        DEFAULT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email  (email),
    INDEX idx_role   (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Courses (Playlists) ──────────────────────────────────────────────────────
CREATE TABLE courses (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(200)    NOT NULL,
    description   TEXT            DEFAULT NULL,
    thumbnail     VARCHAR(255)    DEFAULT NULL,
    is_active     TINYINT(1)      NOT NULL DEFAULT 1,
    created_by    INT UNSIGNED    NOT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active      (is_active),
    INDEX idx_created_by  (created_by),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Course Modules ───────────────────────────────────────────────────────────
CREATE TABLE course_modules (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    course_id     INT UNSIGNED    NOT NULL,
    title         VARCHAR(200)    NOT NULL,
    description   TEXT            DEFAULT NULL,
    type          ENUM('ebook','youtube','quiz') NOT NULL,
    content_data  VARCHAR(500)    DEFAULT NULL COMMENT 'URL for youtube, file path for ebook, null for quiz',
    order_index   INT             NOT NULL DEFAULT 0,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_course (course_id),
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Quiz Questions ───────────────────────────────────────────────────────────
CREATE TABLE quiz_questions (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    module_id     INT UNSIGNED    NOT NULL,
    question_text TEXT            NOT NULL,
    points        INT             NOT NULL DEFAULT 10,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_module (module_id),
    FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Quiz Options ─────────────────────────────────────────────────────────────
CREATE TABLE quiz_options (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    question_id   INT UNSIGNED    NOT NULL,
    option_text   TEXT            NOT NULL,
    is_correct    TINYINT(1)      NOT NULL DEFAULT 0,
    INDEX idx_question (question_id),
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Enrollments ──────────────────────────────────────────────────────────────
CREATE TABLE enrollments (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED    NOT NULL,
    course_id     INT UNSIGNED    NOT NULL,
    progress      TINYINT(3)      NOT NULL DEFAULT 0 COMMENT '0-100 percent based on completed modules',
    completed_at  DATETIME        DEFAULT NULL,
    enrolled_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_enroll (user_id, course_id),
    INDEX idx_user   (user_id),
    INDEX idx_course (course_id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Module Progress ──────────────────────────────────────────────────────────
CREATE TABLE module_progress (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED    NOT NULL,
    module_id     INT UNSIGNED    NOT NULL,
    completed_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_mod_prog (user_id, module_id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Quiz Attempts ────────────────────────────────────────────────────────────
CREATE TABLE quiz_attempts (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED    NOT NULL,
    module_id     INT UNSIGNED    NOT NULL,
    score         INT             NOT NULL DEFAULT 0,
    total_points  INT             NOT NULL DEFAULT 0,
    passed        TINYINT(1)      NOT NULL DEFAULT 0,
    attempted_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_mod (user_id, module_id),
    FOREIGN KEY (user_id)   REFERENCES users(id)   ON DELETE CASCADE,
    FOREIGN KEY (module_id) REFERENCES course_modules(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Achievements ─────────────────────────────────────────────────────────────
CREATE TABLE achievements (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED    NOT NULL,
    title         VARCHAR(200)    NOT NULL,
    description   TEXT            DEFAULT NULL,
    badge_icon    VARCHAR(50)     DEFAULT 'trophy',
    earned_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Rate Limiting ────────────────────────────────────────────────────────────
CREATE TABLE rate_limits (
    id            INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    ip_address    VARCHAR(45)     NOT NULL,
    action        VARCHAR(50)     NOT NULL,
    attempts      SMALLINT        NOT NULL DEFAULT 1,
    window_start  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action (ip_address, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Seed Data ────────────────────────────────────────────────────────────────
-- Default admin: admin@achievehub.com / Admin@123
INSERT INTO users (name, username, email, password, role) VALUES (
    'Administrator',
    'admin',
    'admin@achievehub.com',
    '$2y$12$CKYXIA5sootc3v0N7BKZr.CblfuDGtNsvfk.OD6GoLALePAU5voKm',
    'admin'
);

-- Sample Course
INSERT INTO courses (id, title, description, created_by) VALUES
(1, 'Web Development Bootcamp (Playlist)', 'Learn HTML, CSS, JavaScript, and test your knowledge!', 1);

-- Sample Modules (1 Video, 1 Quiz)
INSERT INTO course_modules (id, course_id, title, description, type, content_data, order_index) VALUES
(1, 1, '1. Intro to Web Dev', 'Basic introduction video', 'youtube', 'https://www.youtube.com/watch?v=qz0aGYrrlhU', 0),
(2, 1, '2. HTML/CSS Deep Dive', 'Learn styling', 'youtube', 'https://www.youtube.com/watch?v=OK_JCtrrv-c', 1),
(3, 1, '3. Basics Assessment', 'Test what you learned so far.', 'quiz', NULL, 2);

-- Sample Quiz Questions for Module 3
INSERT INTO quiz_questions (id, module_id, question_text, points) VALUES
(1, 3, 'What does HTML stand for?', 10),
(2, 3, 'Which CSS property controls text size?', 10);

-- Sample Quiz Options
INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES
(1, 'Hyper Text Markup Language', 1),
(1, 'Home Tool Markup Language', 0),
(1, 'Hyperlinks and Text Markup Language', 0),

(2, 'font-size', 1),
(2, 'text-style', 0),
(2, 'font-weight', 0);
