-- STS FINAL DATABASE SYNC
-- Safe sync for the current Smart Training System.
-- Import this into the existing database: sts
-- It does NOT delete existing users or applications.

USE sts;

-- Users / profile support
ALTER TABLE users
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
        'pengerusi_besar',
        'general_manager',
        'head_of_department'
    ) NOT NULL DEFAULT 'staff';

-- Main application workflow support
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
    ADD COLUMN IF NOT EXISTS review_note TEXT DEFAULT NULL;

ALTER TABLE applications
    MODIFY COLUMN status ENUM(
        'pending',
        'approved',
        'correction',
        'rejected',
        'cancelled'
    ) NOT NULL DEFAULT 'pending';

ALTER TABLE applications
    MODIFY COLUMN current_stage ENUM(
        'hod',
        'training',
        'gm',
        'completed'
    ) NOT NULL DEFAULT 'hod';

-- Approval history
CREATE TABLE IF NOT EXISTS application_reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED NOT NULL,
    review_stage ENUM('hod','training','gm') NOT NULL,
    decision ENUM('approved','rejected','correction') NOT NULL,
    note TEXT DEFAULT NULL,
    reviewed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_reviews_application (application_id),
    INDEX idx_reviews_reviewer (reviewer_id),
    INDEX idx_reviews_stage (review_stage),
    CONSTRAINT fk_reviews_application
      FOREIGN KEY (application_id) REFERENCES applications(id)
      ON DELETE CASCADE,
    CONSTRAINT fk_reviews_user
      FOREIGN KEY (reviewer_id) REFERENCES users(id)
      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Department routing
CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    hod_user_id INT UNSIGNED DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_departments_hod
      FOREIGN KEY (hod_user_id) REFERENCES users(id)
      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Autosave drafts
CREATE TABLE IF NOT EXISTS application_drafts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    form_type ENUM('BPL','PKK','TEA') NOT NULL,
    parent_application_id BIGINT UNSIGNED DEFAULT NULL,
    payload JSON NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_application_drafts_user_type (user_id, form_type),
    CONSTRAINT fk_drafts_user
      FOREIGN KEY (user_id) REFERENCES users(id)
      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Application version history
CREATE TABLE IF NOT EXISTS application_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id BIGINT UNSIGNED NOT NULL,
    actor_user_id INT UNSIGNED DEFAULT NULL,
    event_type VARCHAR(50) NOT NULL,
    payload JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_versions_application (application_id),
    CONSTRAINT fk_versions_application
      FOREIGN KEY (application_id) REFERENCES applications(id)
      ON DELETE CASCADE,
    CONSTRAINT fk_versions_actor
      FOREIGN KEY (actor_user_id) REFERENCES users(id)
      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Supporting attachments
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
    CONSTRAINT fk_attachments_application
      FOREIGN KEY (application_id) REFERENCES applications(id)
      ON DELETE CASCADE,
    CONSTRAINT fk_attachments_user
      FOREIGN KEY (uploaded_by) REFERENCES users(id)
      ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- In-app notifications
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

-- Audit trail
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

-- System settings
CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(500) NOT NULL,
    updated_by INT UNSIGNED DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_settings_user
      FOREIGN KEY (updated_by) REFERENCES users(id)
      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Training/company calendar
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

-- Optional outgoing email queue
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

-- Defaults used by the current application
INSERT INTO system_settings (setting_key, setting_value)
VALUES
  ('review_sla_days', '3'),
  ('pkk_due_days', '7'),
  ('tea_due_days', '30'),
  ('email_notifications', '0'),
  ('mail_from', 'noreply@sts.local')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

SELECT 'STS database is synced with the current feature set.' AS message;
