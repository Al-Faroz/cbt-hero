# CBT-HERO — Deferred Acceptance Master Ujian Akademis

Keputusan: 2026-09-28

## Keputusan Pengujian

Acceptance terperinci **mulai Phase 4D2** tidak dijalankan per subphase untuk sementara.
Implementasi tetap dilanjutkan sampai rangkaian **Master Ujian Akademis** selesai.

Setelah implementasi Master Ujian Akademik **dan** Master Ujian Psikologi selesai,
seluruh acceptance yang ditunda dijalankan sebagai Ujicoba Menyeluruh terpadu.
Tidak ada functional/browser acceptance di tengah batch implementasi, kecuali pemeriksaan
statis atau validasi teknis source yang tidak menjalankan skenario ujian.

Status yang ditunda mulai keputusan ini:

- Phase 4D2 — Excel Template Dinamis;
- Phase 5A — Jadwal Ujian MAIN;
- Phase 5B — Susulan + Kontrol Operasional;
- Phase 5C — Preparation / Prepared Assignment (implementasi selesai; acceptance tetap DEFERRED);
- Phase 6 — Attempt Engine + IndexedDB + Sync + Timer (implementasi selesai; acceptance tetap DEFERRED);
- Phase 7 — Pelaksanaan + Token + Monitoring + Kontrol (implementasi selesai; acceptance tetap DEFERRED);
- Phase 8 — Scoring Akademik + Live Edit + Finalisasi (implementasi selesai; acceptance tetap DEFERRED);
- Participant Renderer + Mobile — PRM01–PRM20 (implementasi/hardening selesai; acceptance device tetap DEFERRED);
- Phase 9A — Hasil Ujian (implementasi selesai; acceptance tetap DEFERRED);
- Phase 9B–9E — Rekap Nilai + Analisis Soal + Export + Public Live Scoring (implementasi selesai; acceptance tetap DEFERRED);
- subphase Master Ujian Akademis berikutnya yang selesai sebelum checkpoint tersebut.

Dokumen acceptance masing-masing tetap menjadi sumber langkah pengujian. Kata
`DEFERRED` berarti **belum diuji**, bukan PASS.

## Update kesiapan 2026-11-25

Batch 1–4 static integration hardening sudah selesai pada checkpoint 2026-10-01.
Batch 5A Pre-Regression Readiness juga selesai pada 2026-11-25:

- inventaris SQL baseline vs legacy upgrade sudah dicatat;
- read-only schema preflight tersedia di `tools/academic_schema_preflight.sql`;
- polish source-level tabel Jadwal dan scroll modal Susulan sudah diimplementasikan;
- parser Excel mendapat hardening edge case.

Seluruh item di atas **tidak mengubah status DEFERRED** acceptance browser/database.
Acceptance mulai Phase 4D2 tetap belum diuji dan harus dijalankan setelah implementasi Phase 10 selesai.

## Checkpoint

Urutan:

```text
Selesaikan seluruh implementasi Master Ujian Akademik
        ↓
Selesaikan seluruh implementasi Master Ujian Psikologi
        ↓
Pre-Test Readiness Review (statis, tanpa skenario ujian)
        ↓
Ujicoba Menyeluruh Akademik + Psikologi
        ↓
Perbaiki seluruh temuan dan retest sampai PASS
```
