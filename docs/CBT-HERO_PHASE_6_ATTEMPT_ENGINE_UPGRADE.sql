-- CBT-HERO Phase 6
-- Jalankan satu kali setelah Phase 5B upgrade.
-- Menyimpan idempotency command kritis Participant: START/RESUME/FINALIZE.

CREATE TABLE IF NOT EXISTS participant_operations (
  idempotency_key VARCHAR(100) PRIMARY KEY,
  peserta_id BIGINT UNSIGNED NOT NULL,
  action VARCHAR(40) NOT NULL,
  jadwal_id BIGINT UNSIGNED NULL,
  attempt_id BIGINT UNSIGNED NULL,
  payload_hash CHAR(64) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'IN_PROGRESS',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at DATETIME NULL,
  KEY idx_participant_operation (peserta_id, action, created_at),
  KEY idx_participant_operation_attempt (attempt_id),
  CONSTRAINT fk_participant_operation_peserta
    FOREIGN KEY (peserta_id) REFERENCES peserta(id) ON DELETE CASCADE,
  CONSTRAINT fk_participant_operation_jadwal
    FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE SET NULL,
  CONSTRAINT fk_participant_operation_attempt
    FOREIGN KEY (attempt_id) REFERENCES attempt(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
