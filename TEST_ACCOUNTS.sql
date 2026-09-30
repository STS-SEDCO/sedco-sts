-- STS TEST ACCOUNTS
-- Import once into database: sts
-- Password for all test accounts: Test123!

USE sts;

INSERT INTO users
(fullname,email,phone_number,staff_id,department,job_title,profile_image,role,is_active,password)
VALUES
('Staff Tester','staff@sts.test',NULL,'TEST-STAFF-001','Corporate Services','Staff',NULL,'staff',1,'$2y$12$JqjNeOmqLOh4Q2uYvrITsuL4wSNmMjMM1ySrkW7LSLPoV9HmQbXo2'),
('HoD Tester','hod@sts.test',NULL,'TEST-HOD-001','Corporate Services','Head of Department',NULL,'head_of_department',1,'$2y$12$JqjNeOmqLOh4Q2uYvrITsuL4wSNmMjMM1ySrkW7LSLPoV9HmQbXo2'),
('Training Tester','training@sts.test',NULL,'TEST-TRAINING-001','Training Department','Training Officer',NULL,'training_section',1,'$2y$12$JqjNeOmqLOh4Q2uYvrITsuL4wSNmMjMM1ySrkW7LSLPoV9HmQbXo2'),
('General Manager Tester','gm@sts.test',NULL,'TEST-GM-001','Management','General Manager',NULL,'general_manager',1,'$2y$12$JqjNeOmqLOh4Q2uYvrITsuL4wSNmMjMM1ySrkW7LSLPoV9HmQbXo2')
ON DUPLICATE KEY UPDATE
fullname=VALUES(fullname),
staff_id=VALUES(staff_id),
department=VALUES(department),
job_title=VALUES(job_title),
role=VALUES(role),
is_active=1,
password=VALUES(password);

INSERT INTO departments (name, hod_user_id, is_active)
VALUES (
  'Corporate Services',
  (SELECT id FROM users WHERE email='hod@sts.test' LIMIT 1),
  1
)
ON DUPLICATE KEY UPDATE
hod_user_id=VALUES(hod_user_id),
is_active=1;

INSERT INTO departments (name, hod_user_id, is_active)
VALUES ('Training Department', NULL, 1)
ON DUPLICATE KEY UPDATE is_active=1;

INSERT INTO departments (name, hod_user_id, is_active)
VALUES ('Management', NULL, 1)
ON DUPLICATE KEY UPDATE is_active=1;

SELECT email, role, department, is_active
FROM users
WHERE email IN ('staff@sts.test','hod@sts.test','training@sts.test','gm@sts.test')
ORDER BY id;
