-- Jalankan setelah CBT-HERO_BANK_COMPOSITION_UPGRADE.sql bila upgrade lama
-- sudah pernah dijalankan sebelum Menjodohkan memakai option_count.
-- Aman dijalankan ulang: hanya mengisi konfigurasi Matching yang masih NULL.

UPDATE bank_type_config
SET option_count = 4
WHERE question_type = 'MATCHING'
  AND option_count IS NULL;
