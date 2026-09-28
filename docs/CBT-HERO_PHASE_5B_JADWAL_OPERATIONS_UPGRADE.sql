-- CBT-HERO Phase 5B
-- Jalankan satu kali pada database yang sudah dibuat sebelum Phase 5B.
-- Menyimpan idempotency untuk command pembuatan Susulan.

CREATE TABLE IF NOT EXISTS jadwal_operations (
  idempotency_key VARCHAR(100) PRIMARY KEY,
  action VARCHAR(40) NOT NULL,
  root_jadwal_id BIGINT UNSIGNED NOT NULL,
  result_jadwal_id BIGINT UNSIGNED NULL,
  payload_hash CHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'IN_PROGRESS',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  KEY idx_jadwal_operation_root (root_jadwal_id, action, created_at),
  CONSTRAINT fk_jadwal_operation_root
    FOREIGN KEY (root_jadwal_id) REFERENCES jadwal(id) ON DELETE RESTRICT,
  CONSTRAINT fk_jadwal_operation_result
    FOREIGN KEY (result_jadwal_id) REFERENCES jadwal(id) ON DELETE SET NULL,
  CONSTRAINT fk_jadwal_operation_actor
    FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
