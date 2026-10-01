# CBT-HERO — Batch 1 Cleanup Lifecycle / Dependency

Tanggal: 2026-10-01

> **Status: IMPLEMENTED / STATIC PASS / FUNCTIONAL REGRESSION DEFERRED**
>
> Batch ini hanya membersihkan lifecycle/dependency Akademik. Pengujian browser,
> database, concurrency, dan end-to-end tetap dilakukan pada checkpoint akhir
> Master Ujian Akademik.

## 1. Invariant yang dikunci

Kegiatan Ujian adalah container administratif.

Status internal Kegiatan:
- boleh tetap ada pada schema untuk kompatibilitas;
- bukan sakelar runtime;
- bukan syarat START/RESUME;
- bukan syarat edit Bank/Soal;
- bukan syarat Monitoring;
- bukan indikator kesiapan operasional.

Gate/lock authoritative:
- state Bank DRAFT/READY untuk editor Bank/Soal;
- Jadwal + waktu + BUKA/TAHAN;
- Preparation;
- Attempt / first START;
- finalisasi hasil;
- dependency data nyata.

## 2. Perbaikan authoritative service

### Participant discovery
Sisa gate `whereIn(k.status, ALLOWED_ACTIVITY_STATUS)` dihapus.

Temuan ini penting karena konstanta `ALLOWED_ACTIVITY_STATUS` sudah tidak ada,
sehingga selain merupakan gate lifecycle lama juga berpotensi menimbulkan error
saat discovery peserta dijalankan.

### Execution dependency
`ExecutionDependencyService::activityStructureLocked()` telah mencakup:
- Prepared Assignment / Preparation;
- first START;
- Attempt;
- hasil final.

Dipakai untuk lock struktur Kegiatan, keanggotaan, Ruang, dan Nomor Peserta.

### Peserta Ujian
Service mengirim `kegiatan.structural_editable` berdasarkan dependency nyata.
UI tidak lagi menentukan editability dari `kegiatan.status === DRAFT`.

### Ruang
Penempatan Ruang menggunakan dependency nyata dan wording lock disinkronkan dengan
Preparation/Attempt/finalisasi.

### Bank / Soal
Editor dan aksi Bank mengikuti state Bank sendiri:
- DRAFT = editor/komposisi/import tersedia;
- READY = editor biasa terkunci;
- kembali ke DRAFT ditolak server bila Bank sudah mempunyai dependency Jadwal.

Status Kegiatan tidak lagi dipakai sebagai kondisi pada:
- Bank Soal;
- Komposisi;
- daftar/editor PG;
- editor advanced;
- import soal.

### Monitoring
Monitoring tidak lagi mencari/memprioritaskan Kegiatan BERJALAN.
Pilihan default memakai Kegiatan yang memiliki Jadwal, tanpa lifecycle Kegiatan.

### Preflight Kegiatan
Preflight administratif tidak lagi menampilkan status Kegiatan sebagai indikator
kesiapan. Kesiapan operasional diarahkan ke Jadwal + Preparation.

## 3. UI cleanup

Sisa client-side gate berbasis Kegiatan DRAFT/BERJALAN telah dihapus dari:
- `manager-peserta-ujian.js`;
- `manager-bank-soal.js`;
- `manager-bank-type-config.js`;
- `manager-question-list.js`;
- `manager-question-pg.js`;
- `manager-question-advanced.js`;
- `manager-monitoring.js`.

Teks lama pada modal hapus soal dan halaman Preflight juga diperbaiki.

## 4. Dokumentasi disinkronkan

Acceptance Phase 3 disesuaikan agar lock berbasis dependency:
- `CBT-HERO_PHASE_3B_PESERTA_UJIAN_ACCEPTANCE.md`;
- `CBT-HERO_PHASE_3C_RUANG_ACCEPTANCE.md`;
- `CBT-HERO_PHASE_3D_NOMOR_PESERTA_ACCEPTANCE.md`;
- `CBT-HERO_PHASE_3F_PREFLIGHT_ACCEPTANCE.md`.

Phase 4 acceptance sebelumnya juga sudah disinkronkan agar status Kegiatan bukan gate.

## 5. Static validation

Hasil sweep service authoritative:
- tidak ada lagi kondisi `where(k.status ...)` / `whereIn(k.status ...)`;
- tidak ada lagi perbandingan `kegiatan_status` untuk keputusan runtime/edit;
- tidak ada lagi `ALLOWED_ACTIVITY_STATUS`;
- brace balance service yang diaudit = normal.

Hasil syntax check JavaScript:
- manager-peserta-ujian.js: OK;
- manager-bank-soal.js: OK;
- manager-bank-type-config.js: OK;
- manager-question-list.js: OK;
- manager-question-pg.js: OK;
- manager-question-advanced.js: OK;
- manager-monitoring.js: OK.

## 6. Yang belum diklaim PASS

Belum dilakukan:
- actual PHP/MySQL execution;
- browser desktop/mobile;
- Preparation nyata;
- lock saat concurrent operation;
- START/RESUME peserta;
- full end-to-end regression.

Karena functional test memang diputuskan dikerjakan belakangan, status Batch 1 adalah:

**IMPLEMENTED / STATIC PASS / FUNCTIONAL REGRESSION DEFERRED**

## 7. Batch berikutnya

Setelah checkpoint ini, urutan berikutnya adalah:

**Batch 2 — scoring/finalisasi**

Fokus:
- scoring enam tipe;
- manual override;
- rescore;
- Live Edit / VOID;
- invariant Scored ≠ Final;
- Finalisasi Hasil;
- snapshot / official-result consistency.
