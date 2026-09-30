-- STS Cancel Application Upgrade
-- Run this ONCE on an existing STS database.

USE sts;

ALTER TABLE applications
    MODIFY COLUMN status ENUM('pending','approved','correction','rejected','cancelled')
        NOT NULL DEFAULT 'pending',
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME DEFAULT NULL AFTER completed_at,
    ADD COLUMN IF NOT EXISTS cancellation_reason TEXT DEFAULT NULL AFTER cancelled_at;

SELECT 'STS cancel application support added successfully.' AS message;
