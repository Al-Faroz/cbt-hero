-- Jalankan sekali di database cbt_hero lama setelah backup.
-- Aman untuk database Phase 2C maupun database yang sudah menerima Phase 2D1.
SET @add_revision = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peserta' AND COLUMN_NAME = 'credential_revision') = 0,
  'ALTER TABLE peserta ADD COLUMN credential_revision INT UNSIGNED NOT NULL DEFAULT 0 AFTER credential_status',
  'SELECT 1'
);
PREPARE statement_revision FROM @add_revision;
EXECUTE statement_revision;
DEALLOCATE PREPARE statement_revision;

-- Catatan idempotensi operasi massal. Fresh import schema revisi tidak perlu ini.
CREATE TABLE IF NOT EXISTS credential_operations (
  idempotency_key VARCHAR(100) PRIMARY KEY,
  action VARCHAR(40) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'IN_PROGRESS',
  affected_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  CONSTRAINT fk_credential_operation_actor FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
