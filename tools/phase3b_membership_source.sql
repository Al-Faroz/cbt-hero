-- Jalankan SEKALI pada database yang sudah memakai schema sebelum revisi Phase 3B.
-- Backup database lebih dahulu. Keanggotaan lama diberi UNKNOWN karena asal
-- selector penugasannya tidak dapat disimpulkan secara akurat dari row lama.
ALTER TABLE peserta_kegiatan
  ADD COLUMN assignment_source VARCHAR(10) NOT NULL DEFAULT 'UNKNOWN' AFTER rombel_snapshot,
  ADD COLUMN assignment_scope VARCHAR(50) NULL AFTER assignment_source,
  ADD KEY idx_pk_kegiatan_source (kegiatan_id, assignment_source, assignment_scope);
