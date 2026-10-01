-- CBT-HERO Phase 9 — Hasil/Laporan/Live Scoring compatibility upgrade
-- Baseline schema terbaru sudah memiliki live_scoring_config.
-- Script ini aman dijalankan pada database lama yang belum mempunyai tabel tersebut.

CREATE TABLE IF NOT EXISTS live_scoring_config (
  id TINYINT UNSIGNED NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  jadwal_id BIGINT UNSIGNED NULL,
  public_token VARCHAR(128) NULL,
  public_token_version INT UNSIGNED NOT NULL DEFAULT 1,
  started_at DATETIME NULL,
  stopped_at DATETIME NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_live_public_token (public_token),
  CONSTRAINT fk_live_jadwal FOREIGN KEY (jadwal_id) REFERENCES jadwal(id) ON DELETE SET NULL,
  CONSTRAINT fk_live_updated_by FOREIGN KEY (updated_by) REFERENCES manager_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO live_scoring_config (id, enabled)
SELECT 1, 0
WHERE NOT EXISTS (SELECT 1 FROM live_scoring_config WHERE id = 1);
