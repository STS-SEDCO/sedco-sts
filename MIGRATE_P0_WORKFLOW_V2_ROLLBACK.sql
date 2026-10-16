-- SEDCO STS P0 rollback (STAGING ONLY; never run blindly).
-- This procedure refuses rollback if any application or historical review
-- currently uses training_final. Decide safe recovery before attempting.
DROP PROCEDURE IF EXISTS sts_p0_rollback_guard;
DELIMITER $$
CREATE PROCEDURE sts_p0_rollback_guard()
BEGIN
  IF EXISTS (SELECT 1 FROM applications WHERE current_stage = 'training_final' LIMIT 1)
     OR EXISTS (SELECT 1 FROM application_reviews WHERE review_stage = 'training_final' LIMIT 1)
  THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Rollback blocked: training_final records exist. Preserve records and restore an approved backup instead.';
  END IF;
END$$
DELIMITER ;
CALL sts_p0_rollback_guard();
DROP PROCEDURE sts_p0_rollback_guard;

ALTER TABLE applications
    MODIFY COLUMN current_stage ENUM(
        'training','hod','gm','chairman','finance','completed'
    ) NOT NULL DEFAULT 'training';

ALTER TABLE application_reviews
    MODIFY COLUMN review_stage ENUM(
        'training','hod','gm','chairman','finance'
    ) NOT NULL;

-- No historical data is removed by this script.
