-- ============================================
-- InventaCCT Full Database Schema
-- City College of Tagaytay
-- Guidance and Counseling Services
-- Generated: 2026-05-13
-- ============================================
--
-- WARNING: Running this will reset the entire database.
-- Comment out the DROP line below if importing into an existing DB.
--
-- DROP DATABASE IF EXISTS cct_inventory;
CREATE DATABASE IF NOT EXISTS cct_inventory
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE cct_inventory;

-- ============================================
-- TABLE: programs
-- (Must be created before sections and users.program_id FK)
-- ============================================
CREATE TABLE IF NOT EXISTS programs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    code       VARCHAR(20)  NOT NULL UNIQUE,   -- e.g. "BSIT"
    name       VARCHAR(100) NOT NULL,           -- e.g. "Bachelor of Science in Information Technology"
    is_active  BOOLEAN      NOT NULL DEFAULT 1,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: users
-- Includes all column additions from:
--   schema.sql, schema_phase3d.sql, schema_sections.sql,
--   schema_sections_v2.sql
-- NOTE: year_level was removed in schema_sections.sql;
--       section now stores the combined format e.g. "3-1"
--       program_id added in schema_sections_v2.sql
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id               INT           AUTO_INCREMENT PRIMARY KEY,
    student_id       VARCHAR(50)   NULL DEFAULT NULL,
    email            VARCHAR(255)  NOT NULL UNIQUE,
    password_hash    VARCHAR(255)  NOT NULL,
    role             ENUM('student','admin') NOT NULL DEFAULT 'student',
    first_name       VARCHAR(100)  NOT NULL,
    middle_initial   VARCHAR(10)   NULL DEFAULT NULL,
    last_name        VARCHAR(100)  NOT NULL,
    program          VARCHAR(50)   NULL DEFAULT NULL,
    program_id       INT           NULL DEFAULT NULL,
    section          VARCHAR(10)   NULL DEFAULT NULL,   -- combined "year-section" e.g. "3-1"
    address          VARCHAR(255)  NULL DEFAULT NULL,
    is_paying_student BOOLEAN      NULL DEFAULT NULL,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_program FOREIGN KEY (program_id)
        REFERENCES programs(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: sections
-- program_id FK added in schema_sections_v2.sql
-- Composite unique key (section_code, program_id)
-- ============================================
CREATE TABLE IF NOT EXISTS sections (
    id           INT          AUTO_INCREMENT PRIMARY KEY,
    section_code VARCHAR(10)  NOT NULL,
    program_id   INT          NOT NULL,
    year_level   TINYINT      NOT NULL,
    is_active    BOOLEAN      NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_section_program (section_code, program_id),
    CONSTRAINT fk_section_program
        FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: inventory_periods
-- Added in schema_phase3e.sql
-- ============================================
CREATE TABLE IF NOT EXISTS inventory_periods (
    id          INT           AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(100)  NOT NULL,
    open_date   DATETIME      NOT NULL,
    close_date  DATETIME      NOT NULL,
    is_active   BOOLEAN       NOT NULL DEFAULT 1,
    extended_by INT           NULL DEFAULT NULL,
    extended_at TIMESTAMP     NULL DEFAULT NULL,
    created_by  INT           NOT NULL,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_period_extended_by
        FOREIGN KEY (extended_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_period_created_by
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: inventory_submissions
-- Includes all column additions from:
--   schema_phase2.sql  — base table
--   schema_phase3b.sql — validity columns
--   schema_phase3c.sql — risk columns
--   schema_phase3d.sql — time_elapsed_seconds
-- ============================================
CREATE TABLE IF NOT EXISTS inventory_submissions (
    id                   INT    AUTO_INCREMENT PRIMARY KEY,
    user_id              INT    NOT NULL,
    submitted_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_complete          BOOLEAN   NOT NULL DEFAULT 0,
    manually_marked      BOOLEAN   NOT NULL DEFAULT 0,
    -- Phase 3b: Validity review
    validity_status      ENUM('valid','requires_review','rejected','resubmit') NOT NULL DEFAULT 'valid',
    validity_flags       JSON      NULL DEFAULT NULL,
    reviewed_by          INT       NULL DEFAULT NULL,
    reviewed_at          TIMESTAMP NULL DEFAULT NULL,
    -- Phase 3c: Risk assessment
    risk_level           ENUM('none','low','moderate','high','urgent') NOT NULL DEFAULT 'none',
    risk_flags           JSON      NULL DEFAULT NULL,
    risk_reviewed        BOOLEAN   NOT NULL DEFAULT 0,
    risk_reviewed_by     INT       NULL DEFAULT NULL,
    risk_reviewed_at     TIMESTAMP NULL DEFAULT NULL,
    -- Phase 3d: Time tracking
    time_elapsed_seconds INT       NULL DEFAULT NULL,
    CONSTRAINT fk_submission_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_submission_reviewed_by
        FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_submission_risk_reviewed_by
        FOREIGN KEY (risk_reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: inventory_answers
-- Added in schema_phase2.sql
-- ============================================
CREATE TABLE IF NOT EXISTS inventory_answers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    section       ENUM('personal_info','learning_style','erq','cat','dass21','ars30','ffmq') NOT NULL,
    question_key  VARCHAR(50)  NOT NULL,
    answer_value  VARCHAR(255) NOT NULL,
    CONSTRAINT fk_answer_submission
        FOREIGN KEY (submission_id) REFERENCES inventory_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: inventory_drafts
-- Added in schema_phase3e.sql
-- ============================================
CREATE TABLE IF NOT EXISTS inventory_drafts (
    id           INT       AUTO_INCREMENT PRIMARY KEY,
    user_id      INT       NOT NULL UNIQUE,
    current_step TINYINT   NOT NULL DEFAULT 1,
    answers      JSON      NOT NULL,
    started_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_saved   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_draft_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABLE: inventory_scores
-- Added in schema_phase3a.sql
-- ============================================
CREATE TABLE IF NOT EXISTS inventory_scores (
    id               INT          AUTO_INCREMENT PRIMARY KEY,
    submission_id    INT          NOT NULL,
    scale            VARCHAR(50)  NOT NULL,
    raw_score        INT          NOT NULL,
    interpretation   VARCHAR(100) NOT NULL,
    needs_counseling BOOLEAN      NOT NULL DEFAULT 0,
    computed_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_score_submission
        FOREIGN KEY (submission_id) REFERENCES inventory_submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INDEXES
-- From schema_search.sql
-- ============================================

-- users table indexes
ALTER TABLE users
    ADD INDEX IF NOT EXISTS idx_users_student_id (student_id),
    ADD INDEX IF NOT EXISTS idx_users_last_name  (last_name),
    ADD INDEX IF NOT EXISTS idx_users_program_id (program_id),
    ADD INDEX IF NOT EXISTS idx_users_section    (section),
    ADD INDEX IF NOT EXISTS idx_users_role       (role);

-- inventory_submissions table indexes
ALTER TABLE inventory_submissions
    ADD INDEX IF NOT EXISTS idx_sub_user_id        (user_id),
    ADD INDEX IF NOT EXISTS idx_sub_is_complete    (is_complete),
    ADD INDEX IF NOT EXISTS idx_sub_risk_level     (risk_level),
    ADD INDEX IF NOT EXISTS idx_sub_validity_status(validity_status);

-- ============================================
-- SEED DATA: programs
-- From schema_sections_v2.sql
-- ============================================
INSERT IGNORE INTO programs (code, name) VALUES
    ('BSIT',   'Bachelor of Science in Information Technology'),
    ('BSCS',   'Bachelor of Science in Computer Science'),
    ('BSA',    'Bachelor of Science in Accountancy'),
    ('BSBA',   'Bachelor of Science in Business Administration'),
    ('BEED',   'Bachelor in Elementary Education'),
    ('BSED',   'Bachelor of Secondary Education'),
    ('BSN',    'Bachelor of Science in Nursing'),
    ('BSCRIM', 'Bachelor of Science in Criminology');

-- ============================================
-- SEED DATA: sections (1-1 through 4-4 for every program)
-- From schema_sections_v2.sql
-- Generates 16 sections × 8 programs = 128 rows
-- Safe to re-run (INSERT IGNORE + unique key)
-- ============================================
INSERT IGNORE INTO sections (section_code, program_id, year_level, is_active)
SELECT
    CONCAT(y.yr, '-', s.sec),
    p.id,
    y.yr,
    1
FROM programs p
CROSS JOIN (SELECT 1 AS yr UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) y
CROSS JOIN (SELECT 1 AS sec UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) s;

-- ============================================
-- SEED DATA: admin account
-- From schema.sql
-- Password hash is for 'admin123' (bcrypt)
-- Change password immediately after first login!
-- ============================================
INSERT INTO users (email, password_hash, role, first_name, last_name)
VALUES (
    'admin@cct.edu.ph',
    '$2y$10$.A4pmFL4r8SUkf/DYOnzwO4j9P2IwBubkAI5yvsjw/xenxPEsXMXG',
    'admin',
    'System',
    'Admin'
)
ON DUPLICATE KEY UPDATE id = id;
