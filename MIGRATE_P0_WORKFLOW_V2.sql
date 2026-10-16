-- SEDCO STS P0: add final Training stage. STAGING FIRST.
-- IMPORTANT: Back up the database and verify application/review counts first.
-- Existing finance review HISTORY remains valid; finance is no longer an active approval stage.
-- The application payload is JSON; new BPL fields require no additional relational columns.
ALTER TABLE applications
    MODIFY COLUMN current_stage ENUM(
        'training','hod','gm','chairman','finance','training_final','completed'
    ) NOT NULL DEFAULT 'training';

ALTER TABLE application_reviews
    MODIFY COLUMN review_stage ENUM(
        'training','hod','gm','chairman','finance','training_final'
    ) NOT NULL;

-- Previously pending Finance requests now await the last Training action.
-- Historical Finance decisions are deliberately NOT rewritten.
UPDATE applications
SET current_stage = 'training_final'
WHERE form_type = 'BPL'
  AND status = 'pending'
  AND current_stage = 'finance';

-- Manual post-migration verification:
SELECT current_stage, status, COUNT(*) AS total
FROM applications
WHERE form_type = 'BPL'
GROUP BY current_stage, status;

SELECT review_stage, COUNT(*) AS total
FROM application_reviews
GROUP BY review_stage;
