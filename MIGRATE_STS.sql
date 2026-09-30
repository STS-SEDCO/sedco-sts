-- Smart Training System (STS) - legacy database migration
-- Target: existing local database named "sts"
-- This script keeps the current users/submissions tables and adds/updates
-- only what the current STS PHP application needs.

USE sts;

-- 1) Bring the legacy users table up to the current STS login schema.
-- XAMPP ships with MariaDB, so ADD COLUMN / ADD INDEX IF NOT EXISTS is supported.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS fullname VARCHAR(120) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS email VARCHAR(190) NULL AFTER fullname,
    ADD COLUMN IF NOT EXISTS phone_number VARCHAR(30) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
        AFTER created_at;

-- Legacy STS used username as a required field. Current signup uses email instead.
ALTER TABLE users
    MODIFY COLUMN username VARCHAR(50) NULL,
    MODIFY COLUMN phone_number VARCHAR(30) NULL,
    MODIFY COLUMN role ENUM(
        'admin',
        'staff',
        'head_of_division',
        'training_section',
        'pengerusi_besar',
        'general_manager',
        'head_of_department'
    ) NOT NULL DEFAULT 'staff';

-- Fill missing values on legacy accounts so the new columns can become required.
UPDATE users
SET fullname = COALESCE(NULLIF(fullname, ''), NULLIF(username, ''), CONCAT('User ', id))
WHERE fullname IS NULL OR fullname = '';

UPDATE users
SET email = CONCAT('legacy-user-', id, '@sts.local')
WHERE email IS NULL OR email = '';

ALTER TABLE users
    MODIFY COLUMN fullname VARCHAR(120) NOT NULL,
    MODIFY COLUMN email VARCHAR(190) NOT NULL;

ALTER TABLE users
    ADD UNIQUE INDEX IF NOT EXISTS uq_users_email (email);

-- 2) Current task table. The old singular "task" table is left untouched.
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Main STS application table used by BPL / PKK / TEA.
-- user_id is INT (signed) to match the legacy users.id in the existing database.
CREATE TABLE IF NOT EXISTS applications (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    application_no VARCHAR(40) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    form_type ENUM('BPL', 'PKK', 'TEA') NOT NULL,
    title VARCHAR(255) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending', 'approved', 'correction', 'rejected') NOT NULL DEFAULT 'pending',
    current_stage ENUM('hod', 'training', 'gm', 'completed') NOT NULL DEFAULT 'hod',
    review_note TEXT DEFAULT NULL,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_applications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    INDEX idx_applications_user (user_id),
    INDEX idx_applications_status (status),
    INDEX idx_applications_stage (current_stage),
    INDEX idx_applications_type (form_type),
    INDEX idx_applications_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) Review history for the approval flow:
-- Staff -> HoD -> Training Department -> GM.
CREATE TABLE IF NOT EXISTS application_reviews (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT NOT NULL,
    reviewer_id INT NOT NULL,
    review_stage ENUM('hod', 'training', 'gm') NOT NULL,
    decision ENUM('approved', 'rejected', 'correction') NOT NULL,
    note TEXT DEFAULT NULL,
    reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reviews_application
        FOREIGN KEY (application_id) REFERENCES applications(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user
        FOREIGN KEY (reviewer_id) REFERENCES users(id)
        ON DELETE RESTRICT,
    INDEX idx_reviews_application (application_id),
    INDEX idx_reviews_reviewer (reviewer_id),
    INDEX idx_reviews_stage (review_stage)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing legacy "submissions" data is intentionally preserved.
-- The current STS code uses "applications" for BPL / PKK / TEA.

SELECT
    'STS database migration completed' AS message,
    (SELECT COUNT(*) FROM users) AS users_count,
    (SELECT COUNT(*) FROM applications) AS applications_count;
