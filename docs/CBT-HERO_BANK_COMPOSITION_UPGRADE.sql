-- Jalankan satu kali pada database yang sudah memakai CBT-HERO_SCHEMA_v1.0.sql lama.
-- Backup database sebelum menjalankan ALTER. Nilai selection_count lama dipertahankan
-- sebagai question_count dan dapat disesuaikan di halaman Komposisi setelah upgrade.

ALTER TABLE bank_type_config
  CHANGE COLUMN selection_count question_count INT UNSIGNED NULL,
  ADD COLUMN option_count TINYINT UNSIGNED NULL AFTER question_count;

-- Existing PG manual menerima 2-6 opsi; nilai awal ini dapat diubah di Komposisi.
UPDATE bank_type_config
SET option_count = 4
WHERE question_type IN ('PG', 'PG_KOMPLEKS', 'PG_BERTINGKAT');

CREATE TABLE jadwal_type_selection (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  jadwal_id BIGINT UNSIGNED NOT NULL,
  question_type VARCHAR(30) NOT NULL,
  selection_count INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_jadwal_type_selection (jadwal_id, question_type),
  CONSTRAINT fk_jadwal_type_selection_jadwal FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
