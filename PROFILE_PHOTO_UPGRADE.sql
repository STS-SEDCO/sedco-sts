-- STS Profile Photo Upgrade
-- Run this ONCE if STS_FEATURES_UPGRADE.sql was already imported before profile photo support was added.

USE sts;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS profile_image VARCHAR(255) DEFAULT NULL AFTER job_title;

SELECT 'STS profile photo support added successfully.' AS message;
