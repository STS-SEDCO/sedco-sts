-- STS Cancel Application Upgrade
-- Safe for older existing STS databases.
-- Run this ONCE on database: sts

USE sts;

ALTER TABLE applications
    MODIFY COLUMN status ENUM('pending','approved','correction','rejected','cancelled')
        NOT NULL DEFAULT 'pending';

ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS completed_at DATETIME DEFAULT NULL;

ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS cancelled_at DATETIME DEFAULT NULL;

ALTER TABLE applications
    ADD COLUMN IF NOT EXISTS cancellation_reason TEXT DEFAULT NULL;

SELECT 'STS cancel application support added successfully.' AS message;
