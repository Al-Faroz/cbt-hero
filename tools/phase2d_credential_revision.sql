-- Jalankan sekali pada database cbt_hero lama sebelum memakai Account Login.
-- Backup database sebelum menjalankan migrasi. Fresh import schema revisi tidak perlu migrasi.
ALTER TABLE peserta
  ADD COLUMN credential_revision BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER credential_status;
