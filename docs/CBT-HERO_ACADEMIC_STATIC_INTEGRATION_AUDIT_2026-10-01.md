# CBT-HERO — Academic Static Integration Audit

Tanggal: 2026-10-01

> **Status: CODE-LEVEL INTEGRATION HARDENING SELESAI / FUNCTIONAL REGRESSION DEFERRED.**
> Dokumen ini bukan tanda PASS fungsional. Pengujian browser, database, multi-device,
> concurrency, dan end-to-end tetap dijalankan pada checkpoint akhir Akademik sebelum
> Master Ujian Psikologi.

## 1. Scope

Audit mengikuti jalur Akademik:

```text
Import Peserta
→ Kegiatan
→ Peserta Ujian
→ Nomor Peserta / Ruang
→ Bank Soal / Import Soal / READY
→ Jadwal
→ Preparation
→ Login Peserta
→ START / RESUME
→ Pengerjaan / Sync / Recovery
→ Submit / Timeout / Paksa Selesai
→ Monitoring
→ Penilaian / Rescore / Live Edit / VOID
→ Finalisasi Hasil
→ Hasil Ujian / Rekap Nilai / Analisis Soal / Export
→ Public Live Scoring
```

Master Ujian Psikologi **tidak** termasuk scope dan belum boleh dimulai.

## 2. Invariant lifecycle Kegiatan

Kegiatan adalah container/atribut. Status administratif Kegiatan tidak boleh menjadi
runtime gate START/RESUME dan tidak boleh menjadi pengganti dependency lock.

Static audit 2026-10-01 menghapus sisa gate lama pada:

- `RuangService`;
- `ParticipantCredentialService`;
- `BankTypeConfigService`;
- `BankReadinessService`;
- `QuestionService`;
- `AdvancedQuestionService`;
- `QuestionImportService`;
- `ParticipantExamDiscoveryService`;
- `MonitoringService`.

Aturan setelah hardening:

- editor/komposisi/import Bank mengikuti state Bank sendiri dan dependency nyata;
- credential Peserta terkunci ketika ada Attempt aktif, bukan karena Kegiatan BERJALAN;
- Ruang menggunakan `ExecutionDependencyService`;
- discovery peserta ditentukan Jadwal + waktu + Preparation + BUKA/TAHAN + Urutan +
  Attempt, bukan status Kegiatan;
- Monitoring menampilkan Jadwal akademik tanpa memerlukan status Kegiatan BERJALAN.

## 3. Participant renderer/mobile

Code-level hardening telah dilakukan sebelum audit integrasi ini:

- enam tipe soal;
- rich table;
- rumus;
- gambar + Panzoom;
- audio/video Google Drive;
- A− / A / A+;
- mobile overflow/touch target;
- debounce Isian/Uraian dengan flush aman;
- snapshot waktu input sebelum debounce;
- palette/modal layering.

Acceptance khusus:

`docs/CBT-HERO_PARTICIPANT_RENDERER_MOBILE_ACCEPTANCE.md`

Status: **IMPLEMENTED / DEVICE REGRESSION DEFERRED**.

## 4. Invariant scoring dan FINAL

Aturan wajib:

```text
Attempt FINISHED ≠ Result FINAL
Scoring COMPLETE ≠ Result FINAL
Paksa Selesai ≠ Finalisasi Hasil
```

Semua snapshot provisional dibuat dengan `is_final = 0`.

Jalur yang menghasilkan snapshot provisional:

- Submit;
- Timeout;
- Paksa Selesai;
- Manual Override;
- Rescore.

Hanya command **Finalisasi Hasil** melalui `ResultFinalizationService` yang membuat
snapshot final dan mengisi `jadwal.results_finalized_at/by`.

Static audit menemukan bug lama pada `forceFinishLocked()`: scoring COMPLETE sempat
langsung menghasilkan `is_final=1`. Implementasi telah direfactor agar
Submit/Timeout/Paksa Selesai menggunakan `ResultSnapshotService` yang sama dan
Paksa Selesai selalu provisional.

Refactor ini sekaligus memastikan snapshot Paksa Selesai tetap menyimpan:

- flag VOID;
- `scoring_state`;
- `scoring_revision_id`;
- official-result pointer secara konsisten.

Acceptance Phase 8 S19 tetap menjadi kontrak authoritative.

## 5. Phase 9 reports

### Hasil Ujian

- sumber: `official_result_pointer`;
- breakdown item terikat ke `scoring_revision_id` yang tersimpan pada snapshot,
  bukan otomatis ke revisi soal saat ini atau baseline Preparation;
- filter Kegiatan/Jadwal/Mapel/Rombel hanya berasal dari official-result Akademik
  yang benar-benar tersedia;
- status FINAL/BELUM FINAL tetap eksplisit.

### Rekap Nilai

- matrix Peserta × Mapel/Jadwal root;
- non-final result tidak lagi menyumbang `final_score` atau rata-rata;
- UI menampilkan `— / PROSES` untuk hasil belum final;
- export mengikuti aturan yang sama.

### Analisis Soal

- agregat memakai `result_item_snapshot`;
- tipe soal berasal dari snapshot;
- metadata detail mengikuti `scoring_revision_id` snapshot;
- `scoring_state` detail berasal dari payload snapshot, bukan state response mutable;
- tetap dapat dihitung sebelum FINAL untuk workflow scoring/live-edit Manager.

### Export Excel/PDF

- Hasil Ujian mengiterasi seluruh pagination sesuai filter, bukan hanya halaman UI;
- Rekap dan Analisis memakai service yang sama dengan layar Manager;
- XLSX dibuat native melalui ZipArchive/XML;
- PDF memakai halaman print / Save as PDF browser.

## 6. Public Live Scoring

Kontrak public tetap tepat 5 kolom:

```text
No | No Peserta | Nama | Skor | Status
```

Public payload tidak membawa:

- ID Jadwal internal;
- username/password/NISN;
- jawaban;
- Attempt detail;
- kunci/rubrik.

Skor hanya kelompok klik:

- PG;
- PG Kompleks;
- PG Bertingkat;
- Menjodohkan.

Fetch snapshot berikutnya dilakukan setelah full scroll cycle.

## 7. Participant finish page

`Tampilkan Nilai Saat Selesai = OFF` tetap menyembunyikan skor.

Saat ON:

- Nilai Pilihan Ganda menampilkan click score bila tersedia;
- Nilai Isian & Uraian menampilkan angka jika COMPLETE;
- menampilkan **DALAM PROSES** jika masih pending manual;
- menampilkan **—** bila ujian memang tidak memiliki Isian Singkat/Uraian.

Tidak ada participant result portal/history menu.

## 8. Dokumentasi yang disinkronkan

Acceptance Phase 4 tidak lagi mensyaratkan Kegiatan DRAFT/BERJALAN sebagai gate:

- `CBT-HERO_PHASE_4A_BANK_SOAL_ACCEPTANCE.md`;
- `CBT-HERO_PHASE_4C1_PG_MANUAL_ACCEPTANCE.md`;
- `CBT-HERO_PHASE_4_AKADEMIK_ACCEPTANCE.md`.

## 9. Yang sengaja belum diklaim PASS

Masih DEFERRED sampai regression akhir:

- actual PHP/MySQL execution;
- migration/upgrade SQL pada database target;
- browser desktop/mobile;
- multi-tab/multi-device;
- offline/reconnect;
- concurrency/idempotency;
- file XLSX dibuka di Excel/LibreOffice;
- Save as PDF;
- public Live Scoring fullscreen panjang;
- seluruh acceptance Phase 2–9;
- end-to-end data nyata ±1.500 peserta.

## 10. Gate berikutnya

Sebelum Master Ujian Psikologi:

1. selesaikan static cleanup Akademik yang tersisa;
2. jalankan SQL upgrade yang belum diterapkan;
3. jalankan acceptance per Phase;
4. jalankan regression end-to-end lengkap;
5. hanya setelah seluruh Akademik PASS, mulai Master Ujian Psikologi.
