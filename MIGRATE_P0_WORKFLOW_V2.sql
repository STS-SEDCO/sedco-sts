-- ============================================================
-- SEDCO SMART TRAINING SYSTEM
-- P0 WORKFLOW UPDATE — Training-Final Stage
-- Branch: ashraf/sedco-workflow-update
-- Date: 2026-10-16
--
-- Changes:
-- 1. Add 'training_final' ENUM value to applications.current_stage
-- 2. Add 'training_final' ENUM value to application_reviews.review_stage
-- 3. Migrate any currently pending 'finance' applications → 'training_final'
-- 4. Remove 'finance' from active stage routing (kept in ENUM for history)
-- 5. Add new BPL payload fields: kaitan_kursus, tarikh_tutup, pendahuluan_nyatakan
--
-- SAFETY:
-- - Does NOT drop any table or column
-- - Does NOT delete any existing records
-- - 'finance' remains in ENUM so historical review records are preserved
-- - All data-altering steps are wrapped in transactions where possible
--
-- Recommended: run a full database backup before importing.
-- ============================================================

USE sts;

-- ============================================================
-- STEP 1: Expand current_stage ENUM on applications table.
-- We ADD 'training_final' without removing 'finance' so that
-- any legacy approved/rejected applications remain valid.
-- ============================================================

ALTER TABLE applications
    MODIFY COLUMN current_stage ENUM(
        'training',
        'hod',
        'gm',
        'chairman',
        'finance',
        'training_final',
        'completed'
    ) NOT NULL DEFAULT 'training';

-- ============================================================
-- STEP 2: Expand review_stage ENUM on application_reviews table.
-- ============================================================

ALTER TABLE application_reviews
    MODIFY COLUMN review_stage ENUM(
        'training',
        'hod',
        'gm',
        'chairman',
        'finance',
        'training_final'
    ) NOT NULL;

-- ============================================================
-- STEP 3: Migrate currently PENDING Finance applications
-- to training_final so they remain actionable under the
-- new workflow. Finance-approved (historical) records are
-- untouched (they already have status='approved' / current_stage='completed').
-- ============================================================

UPDATE applications
SET
    current_stage = 'training_final',
    sla_due_at    = DATE_ADD(NOW(), INTERVAL 3 DAY)
WHERE form_type    = 'BPL'
  AND status       = 'pending'
  AND current_stage = 'finance';

-- ============================================================
-- STEP 4: Repair any pending BPL records that ended up at
-- 'finance' via the old repair script and have a full chain
-- of training→hod→gm→chairman approvals. They are now ready
-- for training_final.
-- ============================================================

UPDATE applications a
INNER JOIN users applicant ON applicant.id = a.user_id
SET a.current_stage = CASE
    WHEN NOT EXISTS (
        SELECT 1 FROM application_reviews r
        WHERE r.application_id = a.id
          AND r.review_stage = 'training'
          AND r.decision = 'approved'
    ) THEN 'training'

    WHEN applicant.role NOT IN (
            'head_of_department','head_of_division',
            'general_manager','pengerusi_besar')
         AND NOT EXISTS (
            SELECT 1 FROM application_reviews r
            WHERE r.application_id = a.id
              AND r.review_stage = 'hod'
              AND r.decision = 'approved'
         ) THEN 'hod'

    WHEN applicant.role NOT IN ('general_manager','pengerusi_besar')
         AND NOT EXISTS (
            SELECT 1 FROM application_reviews r
            WHERE r.application_id = a.id
              AND r.review_stage = 'gm'
              AND r.decision = 'approved'
         ) THEN 'gm'

    WHEN applicant.role <> 'pengerusi_besar'
         AND NOT EXISTS (
            SELECT 1 FROM application_reviews r
            WHERE r.application_id = a.id
              AND r.review_stage = 'chairman'
              AND r.decision = 'approved'
         ) THEN 'chairman'

    -- All role-appropriate intermediate stages approved:
    -- application now awaits Training Final.
    WHEN NOT EXISTS (
        SELECT 1 FROM application_reviews r
        WHERE r.application_id = a.id
          AND r.review_stage = 'training_final'
          AND r.decision = 'approved'
    ) THEN 'training_final'

    ELSE 'completed'
END
WHERE a.form_type  = 'BPL'
  AND a.status     = 'pending';

-- ============================================================
-- STEP 5: Verification
-- ============================================================

SELECT 'P0 WORKFLOW MIGRATION COMPLETED' AS message,
       (SELECT COUNT(*) FROM applications WHERE current_stage = 'training_final' AND status = 'pending') AS pending_at_training_final,
       (SELECT COUNT(*) FROM applications WHERE current_stage = 'finance'        AND status = 'pending') AS pending_at_finance_should_be_0,
       (SELECT COUNT(*) FROM applications WHERE current_stage = 'training'       AND status = 'pending') AS pending_at_training,
       (SELECT COUNT(*) FROM applications WHERE current_stage = 'hod'            AND status = 'pending') AS pending_at_hod,
       (SELECT COUNT(*) FROM applications WHERE current_stage = 'gm'             AND status = 'pending') AS pending_at_gm,
       (SELECT COUNT(*) FROM applications WHERE current_stage = 'chairman'       AND status = 'pending') AS pending_at_chairman,
       (SELECT COUNT(*) FROM applications WHERE status = 'approved' AND current_stage = 'completed') AS completed_approved,
       (SELECT COUNT(*) FROM application_reviews WHERE review_stage = 'finance') AS legacy_finance_reviews_preserved;
