-- CBT-HERO Academic Final — Jadwal Exam Order
-- Jalankan satu kali pada database yang sudah memiliki tabel jadwal.
-- Default 1 mempertahankan perilaku lama: peserta bebas memilih jika semua jadwal
-- pada slot waktu yang sama memiliki urutan 1.

ALTER TABLE jadwal
  ADD COLUMN urutan_ujian TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER jenis_jadwal;

CREATE INDEX idx_jadwal_slot_order
  ON jadwal (kegiatan_id, mulai_at, batas_mulai_at, urutan_ujian);
