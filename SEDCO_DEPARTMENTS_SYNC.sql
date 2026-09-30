-- SEDCO OFFICIAL DIVISIONS SYNC
-- Safe to import into existing database: sts
-- Does not delete any users, applications, or current HoD assignments.

USE sts;

INSERT INTO departments (name, hod_user_id, is_active)
VALUES
  ('Bahagian Audit Dalam (IAD)', NULL, 1),
  ('Bahagian Pembangunan Perniagaan dan Pelaburan (BDI)', NULL, 1),
  ('Bahagian Kewangan (FND)', NULL, 1),
  ('Bahagian Pembangunan Usahawan (EDD)', NULL, 1),
  ('Bahagian Pengurusan Strategik (SMD)', NULL, 1),
  ('Bahagian Pengurusan Hartanah (PMD)', NULL, 1),
  ('Bahagian Sumber Manusia dan Pentadbiran (HRAD)', NULL, 1)
ON DUPLICATE KEY UPDATE
  is_active = 1,
  hod_user_id = hod_user_id;

SELECT name, hod_user_id, is_active
FROM departments
WHERE name IN (
  'Bahagian Audit Dalam (IAD)',
  'Bahagian Pembangunan Perniagaan dan Pelaburan (BDI)',
  'Bahagian Kewangan (FND)',
  'Bahagian Pembangunan Usahawan (EDD)',
  'Bahagian Pengurusan Strategik (SMD)',
  'Bahagian Pengurusan Hartanah (PMD)',
  'Bahagian Sumber Manusia dan Pentadbiran (HRAD)'
)
ORDER BY name;
