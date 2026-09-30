-- STS Profile Upgrade
-- Run this ONCE on the existing "sts" database after the fresh schema was imported.

USE sts;

ALTER TABLE users
    ADD COLUMN staff_id VARCHAR(50) DEFAULT NULL AFTER phone_number,
    ADD COLUMN department VARCHAR(120) DEFAULT NULL AFTER staff_id,
    ADD COLUMN job_title VARCHAR(120) DEFAULT NULL AFTER department,
    ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL AFTER job_title;

ALTER TABLE users
    ADD UNIQUE KEY uq_users_staff_id (staff_id);

SELECT 'STS profile upgrade completed successfully.' AS message;
