-- STS BPL approval flow migration
-- Flow: Training Department -> Head of Department -> General Manager -> Pengerusi -> Kewangan -> Completed
-- Run this once on the existing STS database before using the new approval stages.

USE sts;

ALTER TABLE users
    MODIFY COLUMN role ENUM(
        'admin',
        'staff',
        'head_of_division',
        'training_section',
        'pengerusi_besar',
        'general_manager',
        'head_of_department',
        'finance'
    ) NOT NULL DEFAULT 'staff';

ALTER TABLE applications
    MODIFY COLUMN current_stage ENUM(
        'training',
        'hod',
        'gm',
        'chairman',
        'finance',
        'completed'
    ) NOT NULL DEFAULT 'training';

ALTER TABLE application_reviews
    MODIFY COLUMN review_stage ENUM(
        'training',
        'hod',
        'gm',
        'chairman',
        'finance'
    ) NOT NULL;

-- Repair existing pending BPL records created before the new sequence.
-- A stage is unlocked only after every previous stage has an approved review record.
UPDATE applications a
SET a.current_stage = CASE
    WHEN NOT EXISTS (
        SELECT 1 FROM application_reviews r
        WHERE r.application_id = a.id
          AND r.review_stage = 'training'
          AND r.decision = 'approved'
    ) THEN 'training'
    WHEN NOT EXISTS (
        SELECT 1 FROM application_reviews r
        WHERE r.application_id = a.id
          AND r.review_stage = 'hod'
          AND r.decision = 'approved'
    ) THEN 'hod'
    WHEN NOT EXISTS (
        SELECT 1 FROM application_reviews r
        WHERE r.application_id = a.id
          AND r.review_stage = 'gm'
          AND r.decision = 'approved'
    ) THEN 'gm'
    WHEN NOT EXISTS (
        SELECT 1 FROM application_reviews r
        WHERE r.application_id = a.id
          AND r.review_stage = 'chairman'
          AND r.decision = 'approved'
    ) THEN 'chairman'
    WHEN NOT EXISTS (
        SELECT 1 FROM application_reviews r
        WHERE r.application_id = a.id
          AND r.review_stage = 'finance'
          AND r.decision = 'approved'
    ) THEN 'finance'
    ELSE 'completed'
END
WHERE a.form_type = 'BPL'
  AND a.status = 'pending';

SELECT
    'BPL approval flow migration completed' AS message;
