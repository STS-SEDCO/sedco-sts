-- ============================================================
-- SEDCO SMART TRAINING SYSTEM
-- FULL DATABASE SYNC - 07 OCT 2026
-- For EXISTING database: sts
--
-- Purpose:
-- 1. Add every table/column required by the CURRENT STS code.
-- 2. Keep existing users, applications, reviews and uploaded data.
-- 3. Enable the complete BPL approval flow:
--    Training -> HOD -> GM -> Pengerusi -> Kewangan -> Completed.
-- 4. Include Correction -> Applicant Fix & Resubmit -> Same Review Stage.
-- 5. Include PKK/TEA parent linkage, follow-up reminders and evaluation records.
-- 6. Include Notifications, Approval History, Audit Log, Reports/Analytics support,
--    Calendar, Email Queue, Drafts, Attachments and Version History.
-- 7. Repair legacy pending BPL records that were placed at the wrong stage.
--
-- IMPORTANT:
-- - This script does NOT DROP tables.
-- - This script does NOT delete existing records.
-- - Digital signatures and the new BPL/PKK/TEA form dates are stored inside
--   applications.payload JSON, so no separate signature/date table is required.
-- - Recommended: create a database backup before importing.
-- ============================================================

USE sts;

-- ============================================================
-- 1. USERS / PROFILE / REVIEWER ROLES
-- ============================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS phone_number VARCHAR(30) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS staff_id VARCHAR(50) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS department VARCHAR(120) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS job_title VARCHAR(120) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_active TINYINT(1) NOT NULL DEFAULT 1;

ALTER TABLE users
    MODIFY COLUMN role ENUM(
        'admin',
        'staff',
        'head_of_division',
        'training_section',
        'head_of_department',
        'general_manager',
        'pengerusi_besar',
        'finance'
    ) NOT NULL DEFAULT 'staff';

-- ============================================================
-- 2. MAIN APPLICATION WORKFLOW
-- ============================================================

ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS parent_application_id BIGINT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS department VARCHAR(120) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS assigned_hod_id INT UNSIGNED DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS sla_due_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS training_start DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS training_end DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS followup_due_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS completed_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS cancellation_reason TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS review_note TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE applications
    MODIFY COLUMN form_type ENUM(
        'BPL',
        'PKK',
        'TEA'
    ) NOT NULL,
    MODIFY COLUMN status ENUM(
        'pending',
        'approved',
        'correction',
        'rejected',
        'cancelled'
    ) NOT NULL DEFAULT 'pending';

ALTER TABLE applications
    MODIFY COLUMN current_stage ENUM(
        'training',
        'hod',
        'gm',
        'chairman',
        'finance',
        'completed'
    ) NOT NULL DEFAULT 'training';

ALTER TABLE applications
    ADD INDEX IF NOT EXISTS idx_applications_parent (parent_application_id),
    ADD INDEX IF NOT EXISTS idx_applications_department (department),
    ADD INDEX IF NOT EXISTS idx_applications_hod (assigned_hod_id),
    ADD INDEX IF NOT EXISTS idx_applications_followup_due (followup_due_at),
    ADD INDEX IF NOT EXISTS idx_applications_training_end (training_end);

-- ============================================================
-- 3. APPROVAL / REVIEW HISTORY
-- ============================================================

CREATE TABLE IF NOT EXISTS application_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED NOT NULL,
    review_stage ENUM(
        'training',
        'hod',
        'gm',
        'chairman',
        'finance'
    ) NOT NULL,
    decision ENUM(
        'approved',
        'rejected',
        'correction'
    ) NOT NULL,
    note TEXT DEFAULT NULL,
    reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reviews_application (application_id),
    INDEX idx_reviews_reviewer (reviewer_id),
    INDEX idx_reviews_stage (review_stage),
    INDEX idx_reviews_decision (decision),
    CONSTRAINT fk_reviews_application
      FOREIGN KEY (application_id) REFERENCES applications(id)
      ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user
      FOREIGN KEY (reviewer_id) REFERENCES users(id)
      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Important for databases where application_reviews already existed
-- with an older/partial structure.
ALTER TABLE application_reviews
    ADD COLUMN IF NOT EXISTS note TEXT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFY COLUMN review_stage ENUM(
        'training',
        'hod',
        'gm',
        'chairman',
        'finance'
    ) NOT NULL,
    MODIFY COLUMN decision ENUM(
        'approved',
        'rejected',
        'correction'
    ) NOT NULL;

ALTER TABLE application_reviews
    ADD INDEX IF NOT EXISTS idx_reviews_decision (decision);

-- ============================================================
-- 4. DEPARTMENT ROUTING / HOD ASSIGNMENT
-- ============================================================

CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    hod_user_id INT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_departments_hod
      FOREIGN KEY (hod_user_id) REFERENCES users(id)
      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO departments (name, hod_user_id, is_active)
VALUES
  ('Bahagian Audit Dalam (IAD)', NULL, 1),
  ('Bahagian Pembangunan Perniagaan dan Pelaburan (BDI)', NULL, 1),
  ('Bahagian Kewangan (FND)', NULL, 1),
  ('Bahagian Pembangunan Usahawan (EDD)', NULL, 1),
  ('Bahagian Pengurusan Strategik (SMD)', NULL, 1),
  ('Bahagian Pengurusan Hartanah (PMD)', NULL, 1),
  ('Bahagian Sumber Manusia dan Pentadbiran (HRAD)', NULL, 1)
ON DUPLICATE KEY UPDATE
  is_active = VALUES(is_active);

-- Use the department's configured HOD for existing BPL records
-- where an HOD has not yet been assigned.
UPDATE applications a
INNER JOIN departments d
    ON d.name = a.department
SET a.assigned_hod_id = d.hod_user_id
WHERE a.form_type = 'BPL'
  AND a.assigned_hod_id IS NULL
  AND d.hod_user_id IS NOT NULL;

-- ============================================================
-- 5. DRAFTS / AUTOSAVE
-- ============================================================

CREATE TABLE IF NOT EXISTS application_drafts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    form_type ENUM('BPL','PKK','TEA') NOT NULL,
    parent_application_id BIGINT UNSIGNED DEFAULT NULL,
    payload JSON NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_application_drafts_user_type (user_id, form_type),
    INDEX idx_drafts_parent (parent_application_id),
    CONSTRAINT fk_drafts_user
      FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE application_drafts
    ADD COLUMN IF NOT EXISTS parent_application_id BIGINT UNSIGNED DEFAULT NULL,
    ADD INDEX IF NOT EXISTS idx_drafts_parent (parent_application_id);

-- ============================================================
-- 6. APPLICATION VERSION HISTORY
--    Used for correction/resubmission and PKK editing audit.
-- ============================================================

CREATE TABLE IF NOT EXISTS application_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    event_type VARCHAR(50) NOT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_versions_application (application_id),
    INDEX idx_versions_actor (actor_user_id),
    CONSTRAINT fk_versions_application
      FOREIGN KEY (application_id) REFERENCES applications(id)
      ON DELETE CASCADE,
    CONSTRAINT fk_versions_actor
      FOREIGN KEY (actor_user_id) REFERENCES users(id)
      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 7. APPLICATION ATTACHMENTS
-- ============================================================

CREATE TABLE IF NOT EXISTS application_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attachments_application (application_id),
    INDEX idx_attachments_uploaded_by (uploaded_by),
    CONSTRAINT fk_attachments_application
      FOREIGN KEY (application_id) REFERENCES applications(id)
      ON DELETE CASCADE,
    CONSTRAINT fk_attachments_user
      FOREIGN KEY (uploaded_by) REFERENCES users(id)
      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 8. NOTIFICATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(40) NOT NULL DEFAULT 'info',
    title VARCHAR(180) NOT NULL,
    message VARCHAR(500) NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME DEFAULT NULL,
    INDEX idx_notifications_user_read (user_id, is_read, created_at),
    CONSTRAINT fk_notifications_user
      FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS type VARCHAR(40) NOT NULL DEFAULT 'info',
    ADD COLUMN IF NOT EXISTS title VARCHAR(180) NOT NULL DEFAULT 'Notification',
    ADD COLUMN IF NOT EXISTS message VARCHAR(500) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS link VARCHAR(255) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS is_read TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS read_at DATETIME DEFAULT NULL,
    ADD INDEX IF NOT EXISTS idx_notifications_user_read (user_id, is_read, created_at);

-- ============================================================
-- 9. AUDIT LOG
-- ============================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(50) DEFAULT NULL,
    entity_id VARCHAR(80) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_user (user_id),
    CONSTRAINT fk_audit_user
      FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 10. SYSTEM SETTINGS
-- ============================================================

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(500) NOT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_user
      FOREIGN KEY (updated_by) REFERENCES users(id)
      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO system_settings (setting_key, setting_value)
VALUES
  ('review_sla_days', '3'),
  ('pkk_due_days', '7'),
  ('tea_due_days', '30'),
  ('email_notifications', '0'),
  ('mail_from', 'noreply@sts.local')
ON DUPLICATE KEY UPDATE
  setting_value = setting_value;

-- ============================================================
-- 11. TRAINING / COMPANY CALENDAR
-- ============================================================

CREATE TABLE IF NOT EXISTS calendar_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    event_date DATE NOT NULL,
    event_type VARCHAR(40) NOT NULL DEFAULT 'company',
    description VARCHAR(500) DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_calendar_date (event_date),
    CONSTRAINT fk_calendar_creator
      FOREIGN KEY (created_by) REFERENCES users(id)
      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 12. OPTIONAL OUTGOING EMAIL QUEUE
-- ============================================================

CREATE TABLE IF NOT EXISTS email_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    recipient_email VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME DEFAULT NULL,
    INDEX idx_email_status (status, created_at),
    CONSTRAINT fk_email_user
      FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE email_queue
    ADD COLUMN IF NOT EXISTS status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    ADD COLUMN IF NOT EXISTS attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_error VARCHAR(500) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS sent_at DATETIME DEFAULT NULL,
    ADD INDEX IF NOT EXISTS idx_email_status (status, created_at);

-- ============================================================
-- 13. TASK TABLE USED BY THE CURRENT STS INSTALLATION
-- ============================================================

CREATE TABLE IF NOT EXISTS tasks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
      ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 13A. BACKFILL CURRENT ROUTING / TRAINING DATES
-- ============================================================

UPDATE applications a
INNER JOIN departments d
    ON d.name = a.department
SET a.assigned_hod_id = d.hod_user_id
WHERE a.form_type = 'BPL'
  AND a.assigned_hod_id IS NULL
  AND d.hod_user_id IS NOT NULL;

UPDATE applications
SET training_start = COALESCE(
      training_start,
      NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.tarikh_mula')), '')
    ),
    training_end = COALESCE(
      training_end,
      NULLIF(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.tarikh_tamat')), '')
    )
WHERE form_type = 'BPL'
  AND payload IS NOT NULL;

-- ============================================================
-- 14. REPAIR OLD / STALE BPL APPROVAL STAGES
--
-- Strict rule:
-- Staff: Training -> HOD -> GM -> Pengerusi -> Kewangan.
-- HOD applicant: Training -> GM -> Pengerusi -> Kewangan.
-- GM applicant: Training -> Pengerusi -> Kewangan.
-- Pengerusi applicant: Training -> Kewangan.
-- Training is mandatory for every applicant and nobody approves their own application.
-- ============================================================

UPDATE applications a
INNER JOIN users applicant ON applicant.id = a.user_id
SET a.current_stage = CASE
    WHEN NOT EXISTS (
        SELECT 1
        FROM application_reviews r
        WHERE r.application_id = a.id
          AND r.review_stage = 'training'
          AND r.decision = 'approved'
    ) THEN 'training'

    WHEN applicant.role NOT IN (
            'head_of_department',
            'head_of_division',
            'general_manager',
            'pengerusi_besar'
         )
         AND NOT EXISTS (
            SELECT 1
            FROM application_reviews r
            WHERE r.application_id = a.id
              AND r.review_stage = 'hod'
              AND r.decision = 'approved'
         ) THEN 'hod'

    WHEN applicant.role NOT IN ('general_manager', 'pengerusi_besar')
         AND NOT EXISTS (
            SELECT 1
            FROM application_reviews r
            WHERE r.application_id = a.id
              AND r.review_stage = 'gm'
              AND r.decision = 'approved'
         ) THEN 'gm'

    WHEN applicant.role <> 'pengerusi_besar'
         AND NOT EXISTS (
            SELECT 1
            FROM application_reviews r
            WHERE r.application_id = a.id
              AND r.review_stage = 'chairman'
              AND r.decision = 'approved'
         ) THEN 'chairman'

    WHEN NOT EXISTS (
        SELECT 1
        FROM application_reviews r
        WHERE r.application_id = a.id
          AND r.review_stage = 'finance'
          AND r.decision = 'approved'
    ) THEN 'finance'

    ELSE 'completed'
END
WHERE a.form_type = 'BPL'
  AND a.status = 'pending';

-- ============================================================
-- 15. REPAIR COMPLETED TIMESTAMPS WHEN AVAILABLE
-- ============================================================

UPDATE applications
SET completed_at = COALESCE(completed_at, updated_at)
WHERE status = 'approved'
  AND current_stage = 'completed'
  AND completed_at IS NULL;

-- ============================================================
-- 16. FINAL VERIFICATION
-- ============================================================

SELECT
    'FULL STS DATABASE SYNC COMPLETED - 07 OCT 2026' AS message,
    (SELECT COUNT(*) FROM users) AS users_count,
    (SELECT COUNT(*) FROM applications) AS applications_count,
    (SELECT COUNT(*) FROM application_reviews) AS review_records,
    (SELECT COUNT(*) FROM notifications) AS notifications_count,
    (SELECT COUNT(*) FROM application_versions) AS version_records,
    (SELECT COUNT(*) FROM application_attachments) AS attachment_records,
    (SELECT COUNT(*) FROM application_drafts) AS draft_records,
    (SELECT COUNT(*) FROM departments) AS department_records,
    (SELECT COUNT(*) FROM audit_logs) AS audit_records,
    (SELECT COUNT(*) FROM calendar_events) AS calendar_records,
    (SELECT COUNT(*) FROM email_queue) AS queued_email_records;

SELECT
    current_stage,
    COUNT(*) AS pending_bpl
FROM applications
WHERE form_type = 'BPL'
  AND status = 'pending'
GROUP BY current_stage
ORDER BY FIELD(
    current_stage,
    'training',
    'hod',
    'gm',
    'chairman',
    'finance',
    'completed'
);


-- ============================================================
-- 17. TABLE CHECKLIST
--    Uses SHOW TABLES instead of information_schema so it also
--    works on restricted local/phpMyAdmin accounts.
-- ============================================================

SHOW TABLES;

-- Quick feature verification without information_schema access.
SELECT 'notifications' AS feature, COUNT(*) AS records FROM notifications
UNION ALL
SELECT 'application_reviews', COUNT(*) FROM application_reviews
UNION ALL
SELECT 'application_versions', COUNT(*) FROM application_versions
UNION ALL
SELECT 'application_drafts', COUNT(*) FROM application_drafts
UNION ALL
SELECT 'departments', COUNT(*) FROM departments
UNION ALL
SELECT 'audit_logs', COUNT(*) FROM audit_logs
UNION ALL
SELECT 'calendar_events', COUNT(*) FROM calendar_events
UNION ALL
SELECT 'email_queue', COUNT(*) FROM email_queue;
