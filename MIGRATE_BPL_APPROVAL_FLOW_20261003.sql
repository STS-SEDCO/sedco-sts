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

SELECT
    'BPL approval flow migration completed' AS message;
