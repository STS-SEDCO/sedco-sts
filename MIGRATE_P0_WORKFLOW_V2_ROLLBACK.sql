-- ============================================================
-- SEDCO SMART TRAINING SYSTEM
-- P0 WORKFLOW ROLLBACK — Revert training_final to finance
-- Branch: ashraf/sedco-workflow-update
-- Date: 2026-10-16
--
-- Run this ONLY if you need to revert MIGRATE_P0_WORKFLOW_V2.sql.
--
-- IMPORTANT:
-- - Any applications that received a training_final review after
--   the migration will be reverted to 'finance' stage.
-- - This rollback does NOT delete review records created during
--   the training_final stage (they are kept for audit purposes).
-- ============================================================

USE sts;

-- ============================================================
-- STEP R1: Move pending training_final applications back to finance.
-- ============================================================

UPDATE applications
SET current_stage = 'finance',
    sla_due_at    = DATE_ADD(NOW(), INTERVAL 3 DAY)
WHERE form_type    = 'BPL'
  AND status       = 'pending'
  AND current_stage = 'training_final';

-- ============================================================
-- STEP R2: Revert ENUM columns to exclude training_final.
-- NOTE: Only run after confirming no active records use training_final.
-- If any applications are still at training_final, Step R1 handles them.
-- If you have training_final review history records, they will cause
-- an error on the MODIFY below — in that case skip R3/R4.
-- ============================================================

-- STEP R3 (conditional): Revert applications.current_stage ENUM.
ALTER TABLE applications
    MODIFY COLUMN current_stage ENUM(
        'training',
        'hod',
        'gm',
        'chairman',
        'finance',
        'completed'
    ) NOT NULL DEFAULT 'training';

-- STEP R4 (conditional): Revert application_reviews.review_stage ENUM.
-- WARNING: If any training_final reviews exist, MySQL will error here.
-- This is intentional — do not force-revert if review history exists.
ALTER TABLE application_reviews
    MODIFY COLUMN review_stage ENUM(
        'training',
        'hod',
        'gm',
        'chairman',
        'finance'
    ) NOT NULL;

-- ============================================================
-- STEP R5: Verification
-- ============================================================

SELECT 'P0 WORKFLOW ROLLBACK COMPLETED' AS message,
       (SELECT COUNT(*) FROM applications WHERE current_stage = 'finance'        AND status = 'pending') AS pending_at_finance,
       (SELECT COUNT(*) FROM applications WHERE current_stage = 'training_final' AND status = 'pending') AS pending_at_training_final_should_be_0,
       (SELECT COUNT(*) FROM application_reviews WHERE review_stage = 'training_final') AS training_final_reviews_exist;
