-- Jalankan sekali pada database lama cbt_hero setelah membuat backup.
-- Skema baru dari docs/CBT-HERO_SCHEMA_v1.0.sql sudah memakai kolom ini;
-- jangan jalankan migrasi ini pada database yang baru diimpor dari skema baru.
-- Jika sebuah perintah gagal, berhenti dan periksa data sebelum melanjutkan.

ALTER TABLE kegiatan
  ADD COLUMN tahun_pelajaran VARCHAR(20) NULL AFTER jenis,
  ADD COLUMN semester VARCHAR(20) NULL AFTER tahun_pelajaran;

UPDATE kegiatan AS k
JOIN periode AS p ON p.id = k.periode_id
SET k.tahun_pelajaran = p.tahun_pelajaran,
    k.semester = p.semester;

-- Periksa hasil SELECT berikut: harus 0; jika lebih dari 0, BERHENTI.
SELECT COUNT(*) AS kegiatan_tanpa_konteks
FROM kegiatan
WHERE tahun_pelajaran IS NULL OR semester IS NULL;

-- NOT NULL sengaja ditempatkan sebelum DROP: bila backfill gagal, eksekusi berhenti.
ALTER TABLE kegiatan
  MODIFY tahun_pelajaran VARCHAR(20) NOT NULL,
  MODIFY semester VARCHAR(20) NOT NULL,
  ADD KEY idx_kegiatan_tahun_semester (tahun_pelajaran, semester);

ALTER TABLE kegiatan DROP FOREIGN KEY fk_kegiatan_periode;
ALTER TABLE kegiatan DROP INDEX idx_kegiatan_periode;
ALTER TABLE kegiatan DROP COLUMN periode_id;
DROP TABLE periode;

-- Default Tahun/Semester nantinya diisi melalui Settings ketika UI Kegiatan dibuat.
-- Jangan menyimpulkan default otomatis dari row Periode terakhir: tidak ada status aktif.
