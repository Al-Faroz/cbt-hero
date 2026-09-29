-- CBT-HERO Phase 7 — Pelaksanaan + Token + Monitoring + Kontrol
-- Jalankan setelah upgrade Phase 5B dan Phase 6.

ALTER TABLE token_control
  ADD COLUMN next_token VARCHAR(32) NULL AFTER current_token;

CREATE TABLE IF NOT EXISTS attempt_control_operations (
  idempotency_key VARCHAR(100) PRIMARY KEY,
  action VARCHAR(40) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'IN_PROGRESS',
  affected_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  KEY idx_attempt_control_action (action, created_at),
  CONSTRAINT fk_attempt_control_actor
    FOREIGN KEY (created_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
